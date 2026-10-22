<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Server pools, decommissioning and rebalancing.
 */
class Pools extends Resource
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return array_values(self::asArray($this->client->get('/v3/pools/list')));
    }

    /**
     * @param  string  $pool  pool command line (e.g. "http://server{1...4}/disk{1...4}") or its index when $byId
     * @return array<string, mixed>
     */
    public function status(string $pool, bool $byId = false): array
    {
        return self::asArray($this->client->get('/v3/pools/status', self::poolQuery($pool, $byId)));
    }

    /**
     * @return array<mixed>
     */
    public function decommissionStatus(): array
    {
        return self::asArray($this->client->get('/v3/decommission/status'));
    }

    public function decommission(string $pool, bool $byId = false): mixed
    {
        return $this->client->post('/v3/pools/decommission', self::poolQuery($pool, $byId));
    }

    public function cancelDecommission(string $pool, bool $byId = false): mixed
    {
        return $this->client->post('/v3/pools/cancel', self::poolQuery($pool, $byId));
    }

    public function clearDecommission(string $pool, bool $byId = false): mixed
    {
        return $this->client->post('/v3/pools/clear', self::poolQuery($pool, $byId));
    }

    /**
     * @return array<string, mixed>
     */
    public function startRebalance(): array
    {
        return self::asArray($this->client->post('/v3/rebalance/start'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rebalanceStatus(): array
    {
        return self::asArray($this->client->get('/v3/rebalance/status'));
    }

    public function stopRebalance(): mixed
    {
        return $this->client->post('/v3/rebalance/stop');
    }

    /**
     * @return array<string, mixed>
     */
    private static function poolQuery(string $pool, bool $byId): array
    {
        return ['pool' => $pool, 'by-id' => $byId];
    }
}
