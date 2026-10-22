<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Server configuration (key/value sub-systems, full config and history).
 *
 * KV payloads use the text format "subsys key=value key2=value2", e.g. "notify_webhook:1 enable=on endpoint=...".
 */
class Config extends Resource
{
    /**
     * @param  string  $key  sub-system and optional target, e.g. "api", "notify_webhook:1"
     */
    public function get(string $key): mixed
    {
        return $this->client->get('/v3/get-config-kv', ['key' => $key]);
    }

    /**
     * @return bool whether the change was applied without a restart
     */
    public function set(string $kv): bool
    {
        return $this->applied($this->client->send('PUT', '/v3/set-config-kv', [], $kv)->getHeaderLine('x-rustfs-config-applied'));
    }

    public function delete(string $kv): bool
    {
        return $this->applied($this->client->send('DELETE', '/v3/del-config-kv', [], $kv)->getHeaderLine('x-rustfs-config-applied'));
    }

    /**
     * Help for a sub-system or key.
     */
    public function help(string $subSys = '', ?string $key = null, bool $env = false): mixed
    {
        return $this->client->get('/v3/help-config-kv', self::filter(['subSys' => $subSys, 'key' => $key, 'env' => $env ?: null]));
    }

    /**
     * Full server configuration.
     */
    public function all(): mixed
    {
        return $this->client->get('/v3/config');
    }

    /**
     * Replace the full server configuration.
     */
    public function replace(string $config): mixed
    {
        return $this->client->put('/v3/config', [], $config);
    }

    public function history(int $count = 10): mixed
    {
        return $this->client->get('/v3/list-config-history-kv', ['count' => $count]);
    }

    /**
     * @param  string  $restoreId  history entry id, or "all"
     */
    public function clearHistory(string $restoreId = 'all'): mixed
    {
        return $this->client->delete('/v3/clear-config-history-kv', ['restoreId' => $restoreId]);
    }

    public function restore(string $restoreId): mixed
    {
        return $this->client->put('/v3/restore-config-history-kv', ['restoreId' => $restoreId]);
    }

    /**
     * Enabled/disabled optional server modules.
     *
     * @return array<string, mixed>
     */
    public function moduleSwitches(): array
    {
        return self::asArray($this->client->get('/v3/module-switches'));
    }

    /**
     * Turn the event-notification and/or audit modules on or off. RustFS needs both flags in every
     * request, so a flag left null keeps its current value.
     *
     * @return array<string, mixed>
     */
    public function updateModuleSwitches(?bool $notify = null, ?bool $audit = null): array
    {
        if ($notify === null || $audit === null) {
            $current = $this->moduleSwitches();
            $notify ??= (bool) ($current['notify_enabled'] ?? false);
            $audit ??= (bool) ($current['audit_enabled'] ?? false);
        }

        return self::asArray($this->client->put('/v3/module-switches', [], [
            'notify_enabled' => $notify,
            'audit_enabled' => $audit,
        ]));
    }

    private function applied(string $header): bool
    {
        return strtolower($header) === 'true';
    }
}
