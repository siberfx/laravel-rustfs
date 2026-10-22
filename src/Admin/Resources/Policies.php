<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * IAM canned policies and policy attachments.
 */
class Policies extends Resource
{
    /**
     * Canned policies keyed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function list(?string $bucket = null): array
    {
        return self::asArray($this->client->get('/v3/list-canned-policies', self::filter(['bucket' => $bucket])));
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $name): array
    {
        return self::asArray($this->client->get('/v3/info-canned-policy', ['name' => $name]));
    }

    /**
     * Create or replace a canned policy.
     *
     * @param  array<string, mixed>|string  $document  IAM policy document (array or JSON string)
     */
    public function create(string $name, array|string $document): void
    {
        $this->client->put('/v3/add-canned-policy', ['name' => $name], $document);
    }

    public function delete(string $name): void
    {
        $this->client->delete('/v3/remove-canned-policy', ['name' => $name]);
    }

    /**
     * Replace the policies of a user or group ($policy may be a comma separated list).
     */
    public function set(string $policy, string $userOrGroup, bool $isGroup = false): void
    {
        $this->client->put('/v3/set-user-or-group-policy', ['policyName' => $policy, 'userOrGroup' => $userOrGroup, 'isGroup' => $isGroup]);
    }

    /**
     * Attach policies to one user or one group, keeping the ones already attached.
     *
     * @param  list<string>  $policies
     * @return array<string, mixed>
     */
    public function attach(array $policies, ?string $user = null, ?string $group = null): array
    {
        return self::asArray($this->client->post('/v3/idp/builtin/policy/attach', [], self::association($policies, $user, $group)));
    }

    /**
     * @param  list<string>  $policies
     * @return array<string, mixed>
     */
    public function detach(array $policies, ?string $user = null, ?string $group = null): array
    {
        return self::asArray($this->client->post('/v3/idp/builtin/policy/detach', [], self::association($policies, $user, $group)));
    }

    /**
     * Which users/groups have which policies.
     *
     * @param  list<string>  $users
     * @param  list<string>  $groups
     * @param  list<string>  $policies
     * @return array<string, mixed>
     */
    public function entities(array $users = [], array $groups = [], array $policies = []): array
    {
        return self::asArray($this->client->get('/v3/idp/builtin/policy-entities', [
            'user' => $users,
            'group' => $groups,
            'policy' => $policies,
        ]));
    }

    /**
     * @param  array<string>  $policies
     * @return array<string, mixed>
     */
    private static function association(array $policies, ?string $user, ?string $group): array
    {
        return self::filter(['policies' => array_values($policies), 'user' => $user, 'group' => $group]);
    }
}
