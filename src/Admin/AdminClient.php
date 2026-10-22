<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin;

use Aws\Credentials\Credentials;
use Aws\Signature\S3SignatureV4;
use BackedEnum;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Siberfx\RustFs\ConnectionConfig;
use Siberfx\RustFs\Exceptions\AdminApiException;

/**
 * Low level, SigV4 signed transport for the RustFS Admin API.
 *
 * Paths are relative to the configured admin prefix ("/rustfs/admin"), e.g. "/v3/list-users".
 * Absolute paths that start with "/" and do not begin with "/v" (health, profiling) are sent as-is
 * when passed through {@see self::sendAbsolute()}.
 */
class AdminClient
{
    private readonly ClientInterface $http;

    private readonly S3SignatureV4 $signer;

    private readonly Credentials $credentials;

    public function __construct(
        private readonly ConnectionConfig $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client($config->http + [RequestOptions::HTTP_ERRORS => false]);
        $this->signer = new S3SignatureV4('s3', $config->region);
        $this->credentials = new Credentials($config->key, $config->secret, $config->token);
    }

    public function config(): ConnectionConfig
    {
        return $this->config;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): mixed
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     */
    public function put(string $path, array $query = [], array|string|null $body = null): mixed
    {
        return $this->request('PUT', $path, $query, $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     */
    public function post(string $path, array $query = [], array|string|null $body = null): mixed
    {
        return $this->request('POST', $path, $query, $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     */
    public function delete(string $path, array $query = [], array|string|null $body = null): mixed
    {
        return $this->request('DELETE', $path, $query, $body);
    }

    /**
     * Send a request and decode the answer: JSON becomes an array, an empty body becomes null,
     * anything else (zip archives, text) is returned as a string.
     *
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body  arrays are JSON encoded, strings are sent verbatim
     */
    public function request(string $method, string $path, array $query = [], array|string|null $body = null): mixed
    {
        return self::decode($this->send($method, $path, $query, $body));
    }

    /**
     * Send a request below the admin prefix and return the raw PSR-7 response (2xx only).
     *
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     * @param  array<string, mixed>  $options  extra Guzzle options, e.g. ['stream' => true]
     */
    public function send(string $method, string $path, array $query = [], array|string|null $body = null, array $options = []): ResponseInterface
    {
        return $this->sendAbsolute($method, $this->config->adminPrefix . '/' . ltrim($path, '/'), $query, $body, $options);
    }

    /**
     * Send a signed request to any server path (e.g. "/health/ready", "/profile/cpu").
     *
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     * @param  array<string, mixed>  $options
     */
    public function sendAbsolute(string $method, string $path, array $query = [], array|string|null $body = null, array $options = []): ResponseInterface
    {
        $method = strtoupper($method);
        $headers = [];

        if (is_array($body)) {
            $body = self::encodeJson($body);
            $headers['Content-Type'] = 'application/json';
        } elseif ($body !== null) {
            $headers['Content-Type'] = 'application/octet-stream';
        }

        $uri = $this->config->endpoint . self::encodePath($path);
        $queryString = self::buildQuery($query);
        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        $attempt = 0;
        do {
            // Re-sign every attempt: the signature embeds the request time
            $request = $this->signer->signRequest(new Request($method, $uri, $headers, $body), $this->credentials);
            $response = $this->http->send($request, $options + [RequestOptions::HTTP_ERRORS => false]);
        } while ($response->getStatusCode() === 503 && $attempt++ < $this->config->adminRetries && $this->backoff($attempt, $response));

        if ($response->getStatusCode() >= 400) {
            throw AdminApiException::fromResponse($method, $path, $response);
        }

        return $response;
    }

    /**
     * Wait before retrying a 503; honours Retry-After (seconds) when present.
     */
    private function backoff(int $attempt, ResponseInterface $response): bool
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        $delayMs = ctype_digit($retryAfter) ? (int) $retryAfter * 1000 : $this->config->adminRetryDelayMs * $attempt;
        if ($delayMs > 0) {
            usleep(min($delayMs, 30_000) * 1000);
        }

        return true;
    }

    public static function decode(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();
        if (trim($body) === '') {
            return null;
        }

        $looksLikeJson = str_contains(strtolower($response->getHeaderLine('Content-Type')), 'json')
            || in_array(ltrim($body)[0], ['{', '['], true);

        if ($looksLikeJson) {
            try {
                return json_decode($body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (JsonException) {
                // Fall through and hand back the raw payload
            }
        }

        return $body;
    }

    /**
     * Build a query string. Lists become repeated keys (user=a&user=b) the way RustFS parses them,
     * booleans become "true"/"false" and null values are dropped.
     *
     * @param  array<string, mixed>  $query
     */
    public static function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }
                $item = match (true) {
                    is_bool($item) => $item ? 'true' : 'false',
                    $item instanceof BackedEnum => (string) $item->value,
                    default => (string) $item,
                };
                $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode($item);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * Percent-encode every path segment while keeping the "/" separators.
     */
    public static function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function encodeJson(array $data): string
    {
        // An empty PHP array must reach RustFS as an object, not as a list
        return $data === [] ? '{}' : json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
