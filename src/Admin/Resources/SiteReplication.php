<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Site replication: keep buckets, objects and IAM in sync across independent deployments.
 */
class SiteReplication extends Resource
{
    /**
     * @param  array<array<string, mixed>>  $sites  e.g. [['name' => 'site-a', 'endpoints' => 'https://a:9000', 'accessKey' => ..., 'secretKey' => ...], ...]
     * @param  array<string, mixed>  $query  e.g. ['replicateILMExpiry' => true]
     */
    public function add(array $sites, array $query = []): mixed
    {
        return $this->client->put('/v3/site-replication/add', $query, array_values($sites));
    }

    /**
     * @param  array<string, mixed>  $request  e.g. ['sites' => ['site-b'], 'all' => false]
     */
    public function remove(array $request): mixed
    {
        return $this->client->put('/v3/site-replication/remove', [], $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return self::asArray($this->client->get('/v3/site-replication/info'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function metaInfo(array $query = []): array
    {
        return self::asArray($this->client->get('/v3/site-replication/metainfo', $query));
    }

    /**
     * @param  array<string, mixed>  $query  e.g. ['buckets' => true, 'users' => true, 'entity' => 'bucket', 'entityvalue' => 'photos']
     * @return array<string, mixed>
     */
    public function status(array $query = []): array
    {
        return self::asArray($this->client->get('/v3/site-replication/status', $query));
    }

    /**
     * @param  array<string, mixed>  $peer
     * @param  array<string, mixed>  $query
     */
    public function edit(array $peer, array $query = []): mixed
    {
        return $this->client->put('/v3/site-replication/edit', $query, $peer);
    }

    /**
     * @param  string  $operation  "start" or "cancel"
     * @param  array<string, mixed>  $peer
     */
    public function resync(string $operation, array $peer): mixed
    {
        return $this->client->put('/v3/site-replication/resync/op', ['operation' => $operation], $peer);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function repair(array $payload = []): mixed
    {
        return $this->client->put('/v3/site-replication/repair', [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function repairStatus(array $query = []): mixed
    {
        return $this->client->get('/v3/site-replication/repair/status', $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function editState(array $payload): mixed
    {
        return $this->client->put('/v3/site-replication/state/edit', [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query  e.g. ['duration' => '10s']
     */
    public function netperf(array $query = []): mixed
    {
        return $this->client->post('/v3/site-replication/netperf', $query);
    }
}
