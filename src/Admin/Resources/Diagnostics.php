<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Psr\Http\Message\ResponseInterface;
use Siberfx\RustFs\Admin\Enums\SpeedtestType;

/**
 * Locks, health reports, logs, speed tests, scanner and caches.
 */
class Diagnostics extends Resource
{
    /**
     * @param  array<string, mixed>  $query  e.g. ['count' => 10, 'stale' => true]
     * @return list<array<string, mixed>>
     */
    public function topLocks(array $query = []): array
    {
        return array_values(self::asArray($this->client->get('/v3/top/locks', $query)));
    }

    /**
     * @param  list<string>  $paths  lock resource paths to release
     */
    public function forceUnlock(array $paths): mixed
    {
        return $this->client->post('/v3/force-unlock', ['paths' => implode(',', $paths)]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function healthInfo(array $query = []): mixed
    {
        return $this->client->get('/v3/healthinfo', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function obdInfo(array $query = []): mixed
    {
        return $this->client->get('/v3/obdinfo', $query);
    }

    /**
     * Console log entries (ndjson stream). Pass ['stream' => true] in $options to consume it lazily.
     *
     * @param  array<string, mixed>  $query  e.g. ['node' => '', 'limit' => 100, 'logType' => 'all']
     * @param  array<string, mixed>  $options
     */
    public function log(array $query = [], array $options = []): ResponseInterface
    {
        return $this->client->send('GET', '/v3/log', $query, null, $options);
    }

    /**
     * Run a speed test and return the (ndjson) results.
     *
     * @param  array<string, mixed>  $query  e.g. ['size' => 64 * 1024 * 1024, 'concurrent' => 32, 'duration' => '10s']
     */
    public function speedtest(SpeedtestType $type = SpeedtestType::Object, array $query = []): mixed
    {
        return $this->client->post('/v3/speedtest/' . $type->value, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function scannerStatus(): array
    {
        return self::asArray($this->client->get('/v3/scanner/status'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resetScannerCycleState(array $payload = []): mixed
    {
        return $this->client->post('/v3/scanner/cycle-state/reset', [], $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resetScannerUsageState(array $payload = []): mixed
    {
        return $this->client->post('/v3/scanner/usage-state/reset', [], $payload);
    }

    public function scannerRecoveryIntent(string $intentId): mixed
    {
        return $this->client->get('/v3/scanner/usage-state/recovery-intents/' . $intentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function ilmExpiryStatus(): array
    {
        return self::asArray($this->client->get('/v3/ilm/expiry/status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function objectCacheStats(): array
    {
        return self::asArray($this->client->get('/v3/object-data-cache/stats'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function flushObjectCache(array $payload = []): mixed
    {
        return $this->client->post('/v3/object-data-cache/flush', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayKeyInventory(): array
    {
        return self::asArray($this->client->get('/v3/gateway-key-inventory'));
    }

    /**
     * @return array<string, mixed>
     */
    public function tlsStatus(): array
    {
        return self::asArray($this->client->get('/debug/tls/status'));
    }

    /**
     * Build an inspection archive (returns the archive bytes).
     *
     * @param  array<string, mixed>  $payload
     */
    public function inspectArchive(array $payload): string
    {
        return (string) $this->client->send('POST', '/v4/inspect/archive', [], $payload)->getBody();
    }
}
