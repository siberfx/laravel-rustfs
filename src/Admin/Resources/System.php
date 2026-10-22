<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\Admin\Enums\ServiceAction;

/**
 * Server information, usage and service control.
 */
class System extends Resource
{
    /**
     * Deployment info: mode, region, deploymentID, buckets, objects, usage, backend, servers, pools.
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        $response = $this->rawInfo();

        // RustFS wraps the MinIO-shaped document in an envelope: {"info": {...}, "admin_discovery": {...}}
        return self::asArray($response['info'] ?? $response);
    }

    /**
     * The full /v3/info answer, including the "admin_discovery" and "bitrotSelftest" sections.
     *
     * @return array<string, mixed>
     */
    public function rawInfo(): array
    {
        return self::asArray($this->client->get('/v3/info'));
    }

    /**
     * Drives, their capacity and state, and the backend layout.
     *
     * @return array<string, mixed>
     */
    public function storageInfo(): array
    {
        $response = self::asArray($this->client->get('/v3/storageinfo'));

        // Same envelope as /v3/info
        return self::asArray($response['info'] ?? $response);
    }

    /**
     * Object/byte counts per bucket as computed by the scanner.
     *
     * @return array<string, mixed>
     */
    public function dataUsageInfo(): array
    {
        return self::asArray($this->client->get('/v3/datausageinfo'));
    }

    /**
     * Usage of every prefix in a bucket.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function prefixUsage(string $bucket, array $query = []): array
    {
        return self::asArray($this->client->get('/v3/usage/' . $bucket, $query));
    }

    /**
     * Realtime metrics snapshot (cpu, mem, disk, net, scanner...).
     *
     * @param  array<string, mixed>  $query  e.g. ['types' => 'cpu,mem', 'hosts' => '...']
     */
    public function realtime(array $query = []): mixed
    {
        return $this->client->get('/v3/realtime', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return self::asArray($this->client->get('/v4/runtime/capabilities'));
    }

    /**
     * @return array<string, mixed>
     */
    public function clusterSnapshot(): array
    {
        return self::asArray($this->client->get('/v4/cluster/snapshot'));
    }

    public function service(ServiceAction $action): mixed
    {
        return $this->client->post('/v3/service', ['action' => $action]);
    }

    public function restart(): mixed
    {
        return $this->service(ServiceAction::Restart);
    }

    public function stop(): mixed
    {
        return $this->service(ServiceAction::Stop);
    }

    public function freeze(): mixed
    {
        return $this->service(ServiceAction::Freeze);
    }

    public function unfreeze(): mixed
    {
        return $this->service(ServiceAction::Unfreeze);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function update(array $query = []): mixed
    {
        return $this->client->post('/v3/update', $query);
    }

    /**
     * Download raw data of an on-disk file for support analysis.
     *
     * @return string archive contents
     */
    public function inspectData(string $volume, string $file): string
    {
        return (string) $this->client->send('GET', '/v3/inspect-data', ['volume' => $volume, 'file' => $file])->getBody();
    }
}
