<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Remote storage tiers used by ILM transitions.
 */
class Tiers extends Resource
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return array_values(self::asArray($this->client->get('/v3/tier')));
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(?string $tier = null): array
    {
        return self::asArray($this->client->get('/v3/tier-stats', self::filter(['tier' => $tier])));
    }

    /**
     * Verify the tier is reachable with its stored credentials.
     */
    public function verify(string $name): mixed
    {
        return $this->client->get('/v3/tier/' . $name);
    }

    /**
     * Add a tier, e.g. ['Version' => 'v1', 'Type' => 's3', 'Name' => 'COLD', 'S3' => [...]].
     *
     * @param  array<string, mixed>  $config
     */
    public function add(array $config, bool $force = false): mixed
    {
        return $this->client->put('/v3/tier', $force ? ['force' => true] : [], $config);
    }

    /**
     * Rotate the credentials of a tier.
     *
     * @param  array<string, mixed>  $credentials  e.g. ['access' => ..., 'secret' => ...]
     */
    public function edit(string $name, array $credentials): mixed
    {
        return $this->client->post('/v3/tier/' . $name, [], $credentials);
    }

    public function remove(string $name, bool $force = false): mixed
    {
        return $this->client->delete('/v3/tier/' . $name, $force ? ['force' => true] : []);
    }

    /**
     * Remove every tier. Destructive: objects transitioned to them become unreachable.
     */
    public function clear(): mixed
    {
        return $this->client->post('/v3/tier/clear', ['force' => true]);
    }
}
