<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\S3\Buckets;

/**
 * Bucket replication remote targets, metrics and diagnostics.
 *
 * The replication rules themselves are S3 bucket configuration, see {@see Buckets::putReplication()}.
 */
class Replication extends Resource
{
    /**
     * @param  string|null  $type  e.g. "replication"
     * @return list<array<string, mixed>>
     */
    public function remoteTargets(?string $bucket = null, ?string $type = null): array
    {
        return array_values(self::asArray($this->client->get('/v3/list-remote-targets', self::filter(['bucket' => $bucket, 'type' => $type]))));
    }

    /**
     * Register (or update) a remote target. Returns the target ARN to reference in replication rules.
     *
     * @param  array<string, mixed>  $target  e.g. ['endpoint' => 'host:9000', 'secure' => false, 'targetbucket' => 'dst',
     *                                        'credentials' => ['accessKey' => ..., 'secretKey' => ...], 'type' => 'replication']
     */
    public function setRemoteTarget(string $bucket, array $target, bool $update = false): mixed
    {
        return $this->client->put('/v3/set-remote-target', self::filter(['bucket' => $bucket, 'update' => $update ?: null]), $target);
    }

    public function removeRemoteTarget(string $bucket, string $arn): mixed
    {
        return $this->client->delete('/v3/remove-remote-target', ['bucket' => $bucket, 'arn' => $arn]);
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/replicationmetrics', ['bucket' => $bucket]));
    }

    /**
     * Objects that differ between source and target.
     *
     * @param  array<string, mixed>  $query  e.g. ['arn' => ..., 'prefix' => ..., 'verbose' => true]
     */
    public function diff(string $bucket, array $query = []): mixed
    {
        return $this->client->post('/v3/replication/diff', ['bucket' => $bucket] + $query);
    }

    /**
     * Objects waiting in the most-recently-failed (MRF) retry queue.
     *
     * @param  array<string, mixed>  $query
     */
    public function mrf(string $bucket, array $query = []): mixed
    {
        return $this->client->get('/v3/replication/mrf', ['bucket' => $bucket] + $query);
    }
}
