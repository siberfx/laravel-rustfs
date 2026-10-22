<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\Admin\AdminClient;

/**
 * Event notification targets (webhook, kafka, amqp, mqtt, nats, redis, mysql, postgres, ...)
 * or, when built with the audit base path, audit log targets.
 */
class Targets extends Resource
{
    public const string NOTIFY = '/v3/target';

    public const string AUDIT = '/v3/audit/target';

    public function __construct(AdminClient $client, private readonly string $base = self::NOTIFY)
    {
        parent::__construct($client);
    }

    /**
     * @return array<mixed>
     */
    public function list(): array
    {
        return self::asArray($this->client->get($this->base . '/list'));
    }

    /**
     * Create or update a target, e.g. set('webhook', 'primary', ['endpoint' => 'https://...', 'auth_token' => '...']).
     *
     * $type is the target kind (webhook, kafka, amqp, mqtt, nats, nsq, redis, mysql, postgres, pulsar, elasticsearch).
     * The RustFS sub-system prefix ("notify_" / "audit_") is added for you, and full names such as "notify_webhook" also work.
     *
     * @param  array<string, scalar>  $settings  sub-system keys, e.g. endpoint, auth_token, queue_dir, queue_limit; booleans become on/off
     */
    public function set(string $type, string $name, array $settings): mixed
    {
        $keyValues = [];
        foreach ($settings as $key => $value) {
            $keyValues[] = ['key' => (string) $key, 'value' => is_bool($value) ? ($value ? 'on' : 'off') : (string) $value];
        }

        return $this->client->put($this->base . '/' . $this->subsystem($type) . '/' . $name, [], ['key_values' => $keyValues]);
    }

    public function remove(string $type, string $name): mixed
    {
        return $this->client->delete($this->base . '/' . $this->subsystem($type) . '/' . $name . '/reset');
    }

    /**
     * ARNs to use in bucket notification configurations (notification targets only).
     *
     * @return list<string>
     */
    public function arns(): array
    {
        return array_values(self::asArray($this->client->get(self::NOTIFY . '/arns')));
    }

    /**
     * Buckets/events subscribed to a notification target.
     *
     * @return array<mixed>
     */
    public function subscriptions(string $type, string $name): array
    {
        return self::asArray($this->client->get(self::NOTIFY . '/' . self::prefixed('notify_', $type) . '/' . $name . '/subscriptions'));
    }

    /**
     * The RustFS sub-system name of a target kind, e.g. "webhook" -> "notify_webhook" or "audit_webhook".
     */
    public function subsystem(string $type): string
    {
        return self::prefixed($this->base === self::AUDIT ? 'audit_' : 'notify_', $type);
    }

    private static function prefixed(string $prefix, string $type): string
    {
        $type = strtolower($type);

        return str_starts_with($type, 'notify_') || str_starts_with($type, 'audit_') ? $type : $prefix . $type;
    }
}
