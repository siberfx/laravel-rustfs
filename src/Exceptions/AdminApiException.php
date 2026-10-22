<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Raised when the RustFS Admin API answers with a non-2xx status.
 * RustFS reports errors either as S3-style XML (<Error><Code/><Message/>) or as JSON.
 */
class AdminApiException extends RustFsException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly ?string $requestId = null,
        public readonly string $body = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(string $method, string $path, ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        $code = null;
        $message = null;

        $json = json_decode($body, true);
        if (is_array($json)) {
            $code = $json['Code'] ?? $json['code'] ?? $json['error_code'] ?? null;
            $message = $json['Message'] ?? $json['message'] ?? $json['error'] ?? $json['detail'] ?? null;
        } elseif (str_contains($body, '<Error')) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            libxml_use_internal_errors($previous);
            if ($xml !== false) {
                $code = isset($xml->Code) ? (string) $xml->Code : null;
                $message = isset($xml->Message) ? (string) $xml->Message : null;
            }
        }

        $status = $response->getStatusCode();
        $requestId = $response->getHeaderLine('x-amz-request-id') ?: null;
        $message = is_string($message) && $message !== '' ? $message : ($body !== '' ? mb_strimwidth($body, 0, 300, '...') : $response->getReasonPhrase());

        return new self(
            sprintf('RustFS admin %s %s failed with %d%s: %s', $method, $path, $status, is_string($code) ? " [{$code}]" : '', $message),
            $status,
            is_string($code) ? $code : null,
            $requestId,
            $body,
        );
    }
}
