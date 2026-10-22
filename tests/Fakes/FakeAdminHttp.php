<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Tests\Fakes;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Siberfx\RustFs\ConnectionConfig;
use Siberfx\RustFs\RustFsConnection;

/**
 * Records every request sent through a RustFsConnection and replies with queued responses
 * (default: 200 with "{}").
 */
final class FakeAdminHttp
{
    /** @var list<array{request: RequestInterface}> */
    public array $history = [];

    public readonly MockHandler $mock;

    public readonly Client $client;

    public function __construct()
    {
        $this->mock = new MockHandler;
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            if ($this->mock->count() === 0) {
                $this->mock->append(new Response(200, ['Content-Type' => 'application/json'], '{}'));
            }

            return ($this->mock)($request, $options);
        });
        $stack->push(Middleware::history($this->history));
        $this->client = new Client(['handler' => $stack]);
    }

    public function connection(array $overrides = []): RustFsConnection
    {
        return new RustFsConnection(ConnectionConfig::fromArray($overrides + [
            'endpoint' => 'http://rustfs.test:9000',
            'key' => 'rustfsadmin',
            'secret' => 'rustfssecret',
            'bucket' => 'media',
        ]), $this->client);
    }

    public function queue(Response ...$responses): self
    {
        foreach ($responses as $response) {
            $this->mock->append($response);
        }

        return $this;
    }

    public function last(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function lastQuery(): array
    {
        $query = [];
        foreach (array_filter(explode('&', $this->last()->getUri()->getQuery())) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $query[rawurldecode($key)][] = rawurldecode($value);
        }

        return $query;
    }

    public function lastJson(): mixed
    {
        return json_decode((string) $this->last()->getBody(), true);
    }
}
