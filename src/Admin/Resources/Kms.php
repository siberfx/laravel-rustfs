<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * RustFS KMS: service lifecycle, keys, rotation, re-keying and backups.
 */
class Kms extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return self::asArray($this->client->get('/v3/kms/status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceStatus(): array
    {
        return self::asArray($this->client->get('/v3/kms/service-status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return self::asArray($this->client->get('/v3/kms/config'));
    }

    /**
     * Configure the KMS backend (local, vault-kv2, vault-transit, aws-kms, static ...).
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): mixed
    {
        return $this->client->post('/v3/kms/configure', [], $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function reconfigure(array $config): mixed
    {
        return $this->client->post('/v3/kms/reconfigure', [], $config);
    }

    public function start(): mixed
    {
        return $this->client->post('/v3/kms/start');
    }

    public function stop(): mixed
    {
        return $this->client->post('/v3/kms/stop');
    }

    public function reload(): mixed
    {
        return $this->client->post('/v3/kms/reload');
    }

    public function clearCache(): mixed
    {
        return $this->client->post('/v3/kms/clear-cache');
    }

    /**
     * @param  array<string, string>  $tags
     * @param  string|null  $keyUsage  e.g. "EncryptDecrypt"
     * @return array<string, mixed> includes key_id
     */
    public function createKey(?string $description = null, array $tags = [], ?string $keyUsage = null): array
    {
        return self::asArray($this->client->post('/v3/kms/keys', [], self::filter([
            'key_usage' => $keyUsage,
            'description' => $description,
            'tags' => $tags === [] ? null : $tags,
        ])));
    }

    /**
     * @param  array<string, mixed>  $filters  e.g. ['limit' => 50, 'status' => 'Enabled']
     * @return array<string, mixed>
     */
    public function listKeys(array $filters = []): array
    {
        return self::asArray($this->client->get('/v3/kms/keys', $filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function describeKey(string $keyId): array
    {
        return self::asArray($this->client->get('/v3/kms/keys/' . $keyId));
    }

    /**
     * Schedule key deletion (or delete immediately with $forceImmediate).
     *
     * @return array<string, mixed>
     */
    public function deleteKey(string $keyId, ?int $pendingWindowInDays = null, bool $forceImmediate = false): array
    {
        return self::asArray($this->client->delete('/v3/kms/keys/delete', [], self::filter([
            'key_id' => $keyId,
            'pending_window_in_days' => $pendingWindowInDays,
            'force_immediate' => $forceImmediate ?: null,
            'confirm_key_id' => $forceImmediate ? $keyId : null,
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelKeyDeletion(string $keyId): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/cancel-deletion', [], ['key_id' => $keyId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function enableKey(string $keyId): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/enable', [], ['key_id' => $keyId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function disableKey(string $keyId): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/disable', [], ['key_id' => $keyId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function rotateKey(string $keyId): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/rotate', [], ['key_id' => $keyId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function updateKeyDescription(string $keyId, string $description): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/update-description', [], ['key_id' => $keyId, 'description' => $description]));
    }

    /**
     * @param  array<string, string>  $tags
     * @return array<string, mixed>
     */
    public function tagKey(string $keyId, array $tags): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/tag', [], ['key_id' => $keyId, 'tags' => $tags]));
    }

    /**
     * @param  array<string>  $tagKeys
     * @return array<string, mixed>
     */
    public function untagKey(string $keyId, array $tagKeys): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/untag', [], ['key_id' => $keyId, 'tag_keys' => array_values($tagKeys)]));
    }

    /**
     * @param  array<string, string>  $encryptionContext
     * @param  string  $keySpec  "Aes256" or "Aes128"
     * @return array<string, mixed>
     */
    public function generateDataKey(string $keyId, string $keySpec = 'Aes256', array $encryptionContext = []): array
    {
        return self::asArray($this->client->post('/v3/kms/generate-data-key', [], self::filter([
            'key_id' => $keyId,
            'key_spec' => $keySpec,
            'encryption_context' => $encryptionContext === [] ? null : $encryptionContext,
        ])));
    }

    /**
     * Re-encrypt object keys that were sealed with an old key version.
     *
     * @param  array<string, mixed>  $payload  e.g. ['key_id' => ..., 'bucket' => ...]
     * @return array<string, mixed>
     */
    public function startRekey(array $payload): array
    {
        return self::asArray($this->client->post('/v3/kms/keys/rekey', [], $payload));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function rekeyStatus(array $query = []): array
    {
        return self::asArray($this->client->get('/v3/kms/keys/rekey/status', $query));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function cancelRekey(array $payload = []): mixed
    {
        return $this->client->post('/v3/kms/keys/rekey/cancel', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function backupStatus(): array
    {
        return self::asArray($this->client->get('/v3/kms/backup'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function backup(array $payload = []): mixed
    {
        return $this->client->post('/v3/kms/backup', [], $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function restore(array $payload, bool $dryRun = false): mixed
    {
        return $this->client->post($dryRun ? '/v3/kms/restore/dry-run' : '/v3/kms/restore', [], $payload);
    }

    public function abortRestore(): mixed
    {
        return $this->client->post('/v3/kms/restore/abort');
    }
}
