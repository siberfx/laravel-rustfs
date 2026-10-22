<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * The calling identity: account info, password and multi-factor authentication.
 */
class Account extends Resource
{
    /**
     * Buckets visible to the caller with their access rights and usage.
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return self::asArray($this->client->get('/v3/accountinfo'));
    }

    /**
     * @return array<string, mixed>
     */
    public function self(): array
    {
        return self::asArray($this->client->get('/v3/account/info'));
    }

    public function changePassword(string $currentSecretKey, string $newSecretKey): mixed
    {
        return $this->client->post('/v3/account/password', [], [
            'current_secret_key' => $currentSecretKey,
            'new_secret_key' => $newSecretKey,
        ]);
    }

    public function isAdmin(): bool
    {
        return (bool) (self::asArray($this->client->get('/v3/is-admin'))['is_admin'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function mfaStatus(): array
    {
        return self::asArray($this->client->get('/v3/account/mfa'));
    }

    /**
     * Start TOTP enrollment; the answer carries the secret / provisioning URI.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrollMfa(array $payload = []): array
    {
        return self::asArray($this->client->post('/v3/account/mfa/enroll', [], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload  e.g. ['code' => '123456']
     * @return array<string, mixed>
     */
    public function activateMfa(array $payload): array
    {
        return self::asArray($this->client->post('/v3/account/mfa/activate', [], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function disableMfa(array $payload = []): array
    {
        return self::asArray($this->client->post('/v3/account/mfa/disable', [], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function regenerateRecoveryCodes(array $payload = []): array
    {
        return self::asArray($this->client->post('/v3/account/mfa/recovery-codes', [], $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function mfaChallenge(): array
    {
        return self::asArray($this->client->get('/v3/mfa/challenge'));
    }
}
