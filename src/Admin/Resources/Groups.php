<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\Admin\Enums\AccountStatus;

/**
 * IAM groups.
 */
class Groups extends Resource
{
    /**
     * @return list<string>
     */
    public function list(): array
    {
        return array_values(self::asArray($this->client->get('/v3/groups')));
    }

    /**
     * @return array<string, mixed> name, status, members, policy
     */
    public function info(string $group): array
    {
        return self::asArray($this->client->get('/v3/group', ['group' => $group]));
    }

    /**
     * Groups are created implicitly when members are added.
     *
     * @param  list<string>  $members
     */
    public function create(string $group, array $members = []): void
    {
        $this->addMembers($group, $members);
    }

    /**
     * @param  list<string>  $members
     */
    public function addMembers(string $group, array $members): void
    {
        $this->updateMembers($group, $members, false);
    }

    /**
     * @param  list<string>  $members
     */
    public function removeMembers(string $group, array $members): void
    {
        $this->updateMembers($group, $members, true);
    }

    public function delete(string $group): void
    {
        $this->client->delete('/v3/group/' . $group);
    }

    public function setStatus(string $group, AccountStatus $status): void
    {
        $this->client->put('/v3/set-group-status', ['group' => $group, 'status' => $status->value]);
    }

    public function enable(string $group): void
    {
        $this->setStatus($group, AccountStatus::Enabled);
    }

    public function disable(string $group): void
    {
        $this->setStatus($group, AccountStatus::Disabled);
    }

    public function setPolicy(string $group, string $policy): void
    {
        $this->client->put('/v3/set-user-or-group-policy', ['policyName' => $policy, 'userOrGroup' => $group, 'isGroup' => true]);
    }

    /**
     * @param  array<string>  $members
     */
    private function updateMembers(string $group, array $members, bool $remove): void
    {
        $this->client->put('/v3/update-group-members', [], [
            'group' => $group,
            'members' => array_values($members),
            'groupStatus' => AccountStatus::Enabled->value,
            'isRemove' => $remove,
        ]);
    }
}
