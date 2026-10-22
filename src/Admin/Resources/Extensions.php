<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Plugin and extension catalog / instances (Admin API v4).
 */
class Extensions extends Resource
{
    /**
     * @return array<mixed>
     */
    public function pluginCatalog(): array
    {
        return self::asArray($this->client->get('/v4/plugins/catalog'));
    }

    /**
     * @return array<mixed>
     */
    public function plugins(): array
    {
        return self::asArray($this->client->get('/v4/plugins/instances'));
    }

    /**
     * @return array<string, mixed>
     */
    public function plugin(string $id): array
    {
        return self::asArray($this->client->get('/v4/plugins/instances/' . $id));
    }

    /**
     * @param  array<string, mixed>  $instance
     * @return array<string, mixed>
     */
    public function putPlugin(string $id, array $instance): array
    {
        return self::asArray($this->client->put('/v4/plugins/instances/' . $id, [], $instance));
    }

    public function deletePlugin(string $id): mixed
    {
        return $this->client->delete('/v4/plugins/instances/' . $id);
    }

    /**
     * @return array<mixed>
     */
    public function extensionCatalog(): array
    {
        return self::asArray($this->client->get('/v4/extensions/catalog'));
    }

    /**
     * @return array<mixed>
     */
    public function extensions(): array
    {
        return self::asArray($this->client->get('/v4/extensions/instances'));
    }
}
