<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\Admin\Enums\QuotaType;

/**
 * Bucket quotas.
 */
class Quotas extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/quota/' . $bucket));
    }

    /**
     * @param  int  $bytes  maximum bucket size in bytes
     * @return array<string, mixed>
     */
    public function set(string $bucket, int $bytes, QuotaType $type = QuotaType::Hard): array
    {
        return self::asArray($this->client->put('/v3/quota/' . $bucket, [], [
            'quota' => $bytes,
            'quota_type' => $type->value,
        ]));
    }

    public function clear(string $bucket): mixed
    {
        return $this->client->delete('/v3/quota/' . $bucket);
    }

    /**
     * @return array<string, mixed> quota, current usage, remaining
     */
    public function stats(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/quota-stats/' . $bucket));
    }

    /**
     * Ask whether an operation of $size bytes would fit in the quota.
     *
     * @return array<string, mixed> bucket, operation_type, operation_size, allowed, ...
     */
    public function check(string $bucket, int $size, string $operationType = 'PUT'): array
    {
        return self::asArray($this->client->post('/v3/quota-check/' . $bucket, [], [
            'operation_type' => $operationType,
            'operation_size' => $size,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function migrateProtocol(array $payload = []): array
    {
        return self::asArray($this->client->post('/v3/quota-migration', [], $payload));
    }
}
