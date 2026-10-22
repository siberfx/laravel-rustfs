<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use DateTimeInterface;

/**
 * Service accounts (access keys bound to a parent user).
 */
class ServiceAccounts extends Resource
{
    /**
     * Create a service account. Leave $accessKey/$secretKey empty to let RustFS generate them.
     *
     * @param  array<string, mixed>|null  $policy  optional session policy narrowing the parent permissions
     * @return array<string, mixed> the credentials (accessKey, secretKey, expiration)
     */
    public function create(
        ?string $targetUser = null,
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?array $policy = null,
        ?string $name = null,
        ?string $description = null,
        ?DateTimeInterface $expiration = null,
        ?string $comment = null,
    ): array {
        $response = self::asArray($this->client->put('/v3/add-service-account', [], self::filter([
            'targetUser' => $targetUser,
            'accessKey' => $accessKey ?? '',
            'secretKey' => $secretKey ?? '',
            'policy' => $policy,
            'name' => $name,
            'description' => $description,
            'expiration' => $expiration?->format(DATE_RFC3339),
            'comment' => $comment,
        ])));

        return self::asArray($response['credentials'] ?? $response);
    }

    /**
     * @param  array<string, mixed>|null  $newPolicy
     * @param  string|null  $newStatus  "on" or "off"
     */
    public function update(
        string $accessKey,
        ?array $newPolicy = null,
        ?string $newSecretKey = null,
        ?string $newStatus = null,
        ?string $newName = null,
        ?string $newDescription = null,
        ?DateTimeInterface $newExpiration = null,
    ): void {
        $this->client->post('/v3/update-service-account', ['accessKey' => $accessKey], self::filter([
            'newPolicy' => $newPolicy,
            'newSecretKey' => $newSecretKey,
            'newStatus' => $newStatus,
            'newName' => $newName,
            'newDescription' => $newDescription,
            'newExpiration' => $newExpiration?->format(DATE_RFC3339),
        ]));
    }

    public function enable(string $accessKey): void
    {
        $this->update($accessKey, newStatus: 'on');
    }

    public function disable(string $accessKey): void
    {
        $this->update($accessKey, newStatus: 'off');
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $accessKey): array
    {
        return self::asArray($this->client->get('/v3/info-service-account', ['accessKey' => $accessKey]));
    }

    /**
     * Service accounts of a user (defaults to the caller).
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $user = null): array
    {
        $response = self::asArray($this->client->get('/v3/list-service-accounts', self::filter(['user' => $user])));

        return array_values(self::asArray($response['accounts'] ?? []));
    }

    public function delete(string $accessKey): void
    {
        $this->client->delete('/v3/delete-service-account', ['accessKey' => $accessKey]);
    }

    /**
     * Information about any access key (user, service account or STS).
     *
     * @return array<string, mixed>
     */
    public function accessKeyInfo(string $accessKey): array
    {
        return self::asArray($this->client->get('/v3/info-access-key', ['accessKey' => $accessKey]));
    }

    /**
     * @return array<string, mixed>
     */
    public function temporaryAccountInfo(string $accessKey): array
    {
        return self::asArray($this->client->get('/v3/temporary-account-info', ['accessKey' => $accessKey]));
    }

    /**
     * List the access keys of many users at once.
     *
     * @param  list<string>  $users
     * @param  string  $listType  "all", "sts-only" or "svcacc-only"
     * @return array<string, mixed>
     */
    public function listBulk(array $users = [], bool $all = false, string $listType = 'all'): array
    {
        return self::asArray($this->client->get('/v3/list-access-keys-bulk', self::filter([
            'users' => $users === [] ? null : implode(',', $users),
            'all' => $all,
            'listType' => $listType,
        ])));
    }
}
