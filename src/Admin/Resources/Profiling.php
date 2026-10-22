<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP/API tracing and CPU/memory profiling.
 */
class Profiling extends Resource
{
    /**
     * Open the live trace stream (ndjson). The response body is streamed; read it with
     * {@see self::traceEvents()} or $response->getBody()->read().
     *
     * @param  array<string, mixed>  $query  e.g. ['s3' => true, 'internal' => false, 'err' => true, 'threshold' => '100ms']
     */
    public function trace(array $query = []): ResponseInterface
    {
        return $this->client->send('GET', '/v3/trace', $query, null, ['stream' => true, 'read_timeout' => 0, 'timeout' => 0]);
    }

    /**
     * Iterate over decoded trace events until the stream closes or $limit events were read.
     *
     * @param  array<string, mixed>  $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function traceEvents(array $query = [], ?int $limit = null): \Generator
    {
        $stream = $this->trace($query)->getBody();
        $buffer = '';
        $count = 0;

        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') {
                    continue;
                }
                $event = json_decode($line, true);
                if (is_array($event)) {
                    yield $event;
                    if ($limit !== null && ++$count >= $limit) {
                        $stream->close();

                        return;
                    }
                }
            }
        }
    }

    /**
     * Capture a profile and return the raw pprof bytes.
     *
     * @param  array<string, mixed>  $query  handler options, e.g. ['seconds' => 30]
     */
    public function profile(array $query = []): string
    {
        return (string) $this->client->send('GET', '/debug/pprof/profile', $query)->getBody();
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return self::asArray($this->client->get('/debug/pprof/status'));
    }

    /**
     * @param  string  $profilerType  comma separated, e.g. "cpu,mem"
     */
    public function start(string $profilerType = 'cpu'): mixed
    {
        return $this->client->post('/v3/profiling/start', ['profilerType' => $profilerType]);
    }

    /**
     * @return string zip archive with the collected profiles
     */
    public function download(): string
    {
        return (string) $this->client->send('GET', '/v3/profiling/download')->getBody();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function control(array $payload): mixed
    {
        return $this->client->post('/v3/profile', [], $payload);
    }
}
