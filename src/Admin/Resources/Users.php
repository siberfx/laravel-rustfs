<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\Admin\Enums\AccountStatus;

/**
 * IAM users (built-in identity provider).
 */
class Users extends Resource
{
    /**
     * All users keyed by access key, optionally only those with access to a bucket.
     *
     * @return array<string, array<string, mixed>>
     */
    public function list(?string $bucket = null): array
    {
        return self::asArray($this->client->get('/v3/list-users', self::filter(['bucket' => $bucket])));
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $accessKey): array
    {
        return self::asArray($this->client->get('/v3/user-info', ['accessKey' => $accessKey]));
    }

    public function exists(string $accessKey): bool
    {
        return array_key_exists($accessKey, $this->list());
    }

    /**
     * Create a user, or update an existing one (secret and status are replaced).
     *
     * @param  string|null  $policy  canned policy (or comma separated list) to attach after creation
     */
    public function create(string $accessKey, string $secretKey, ?string $policy = null, AccountStatus $status = AccountStatus::Enabled): void
    {
        $this->client->put('/v3/add-user', ['accessKey' => $accessKey], [
            'secretKey' => $secretKey,
            'status' => $status->value,
        ]);

        // RustFS 1.0.x ignores the "policy" field of add-user, so the policy is attached explicitly
        if ($policy !== null && $policy !== '') {
            $this->setPolicy($accessKey, $policy);
        }
    }

    public function delete(string $accessKey): void
    {
        $this->client->delete('/v3/remove-user', ['accessKey' => $accessKey]);
    }

    public function setStatus(string $accessKey, AccountStatus $status): void
    {
        $this->client->put('/v3/set-user-status', ['accessKey' => $accessKey, 'status' => $status->value]);
    }

    public function enable(string $accessKey): void
    {
        $this->setStatus($accessKey, AccountStatus::Enabled);
    }

    public function disable(string $accessKey): void
    {
        $this->setStatus($accessKey, AccountStatus::Disabled);
    }

    /**
     * Reset the secret key of a user while keeping status, policies and group memberships.
     */
    public function setSecretKey(string $accessKey, string $secretKey): void
    {
        $this->client->put('/v3/set-user-secret-key', ['accessKey' => $accessKey], ['secret_key' => $secretKey]);
    }

    /**
     * Replace the canned policies of the user ($policy may be a comma separated list).
     */
    public function setPolicy(string $accessKey, string $policy): void
    {
        $this->client->put('/v3/set-user-or-group-policy', ['policyName' => $policy, 'userOrGroup' => $accessKey, 'isGroup' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    public function mfaStatus(string $accessKey): array
    {
        return self::asArray($this->client->get('/v3/user/mfa', ['accessKey' => $accessKey]));
    }

    public function resetMfa(string $accessKey): void
    {
        $this->client->delete('/v3/user/mfa', ['accessKey' => $accessKey]);
    }
}
