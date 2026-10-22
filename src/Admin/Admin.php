<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin;

use Psr\Http\Message\ResponseInterface;
use Siberfx\RustFs\Admin\Resources\Account;
use Siberfx\RustFs\Admin\Resources\BucketAdmin;
use Siberfx\RustFs\Admin\Resources\Config;
use Siberfx\RustFs\Admin\Resources\Diagnostics;
use Siberfx\RustFs\Admin\Resources\Extensions;
use Siberfx\RustFs\Admin\Resources\Groups;
use Siberfx\RustFs\Admin\Resources\Heal;
use Siberfx\RustFs\Admin\Resources\Iam;
use Siberfx\RustFs\Admin\Resources\Ilm;
use Siberfx\RustFs\Admin\Resources\Kms;
use Siberfx\RustFs\Admin\Resources\Policies;
use Siberfx\RustFs\Admin\Resources\Pools;
use Siberfx\RustFs\Admin\Resources\Profiling;
use Siberfx\RustFs\Admin\Resources\Quotas;
use Siberfx\RustFs\Admin\Resources\Replication;
use Siberfx\RustFs\Admin\Resources\ServiceAccounts;
use Siberfx\RustFs\Admin\Resources\SiteReplication;
use Siberfx\RustFs\Admin\Resources\System;
use Siberfx\RustFs\Admin\Resources\Targets;
use Siberfx\RustFs\Admin\Resources\Tiers;
use Siberfx\RustFs\Admin\Resources\Users;

/**
 * Entry point to the RustFS Admin API, grouped by resource.
 *
 * Anything without a dedicated method can be reached through {@see self::request()}.
 */
class Admin
{
    /** @var array<class-string, object> */
    private array $resources = [];

    private ?Targets $notificationTargets = null;

    private ?Targets $auditTargets = null;

    public function __construct(private readonly AdminClient $client) {}

    public function client(): AdminClient
    {
        return $this->client;
    }

    public function users(): Users
    {
        return $this->resource(Users::class);
    }

    public function groups(): Groups
    {
        return $this->resource(Groups::class);
    }

    public function policies(): Policies
    {
        return $this->resource(Policies::class);
    }

    public function serviceAccounts(): ServiceAccounts
    {
        return $this->resource(ServiceAccounts::class);
    }

    public function account(): Account
    {
        return $this->resource(Account::class);
    }

    public function iam(): Iam
    {
        return $this->resource(Iam::class);
    }

    public function system(): System
    {
        return $this->resource(System::class);
    }

    public function quotas(): Quotas
    {
        return $this->resource(Quotas::class);
    }

    public function tiers(): Tiers
    {
        return $this->resource(Tiers::class);
    }

    public function pools(): Pools
    {
        return $this->resource(Pools::class);
    }

    public function heal(): Heal
    {
        return $this->resource(Heal::class);
    }

    public function kms(): Kms
    {
        return $this->resource(Kms::class);
    }

    public function replication(): Replication
    {
        return $this->resource(Replication::class);
    }

    public function siteReplication(): SiteReplication
    {
        return $this->resource(SiteReplication::class);
    }

    public function config(): Config
    {
        return $this->resource(Config::class);
    }

    public function buckets(): BucketAdmin
    {
        return $this->resource(BucketAdmin::class);
    }

    public function ilm(): Ilm
    {
        return $this->resource(Ilm::class);
    }

    public function diagnostics(): Diagnostics
    {
        return $this->resource(Diagnostics::class);
    }

    public function profiling(): Profiling
    {
        return $this->resource(Profiling::class);
    }

    public function extensions(): Extensions
    {
        return $this->resource(Extensions::class);
    }

    /**
     * Event notification targets (webhook, kafka, amqp, mqtt, nats, redis, ...).
     */
    public function notifications(): Targets
    {
        return $this->notificationTargets ??= new Targets($this->client, Targets::NOTIFY);
    }

    /**
     * Audit log targets.
     */
    public function audit(): Targets
    {
        return $this->auditTargets ??= new Targets($this->client, Targets::AUDIT);
    }

    /**
     * Call any admin route, e.g. request('GET', '/v3/top/locks', ['count' => 5]).
     *
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     */
    public function request(string $method, string $path, array $query = [], array|string|null $body = null): mixed
    {
        return $this->client->request($method, $path, $query, $body);
    }

    /**
     * Like {@see self::request()} but returns the raw response (for streams and binary downloads).
     *
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|string|null  $body
     * @param  array<string, mixed>  $options
     */
    public function send(string $method, string $path, array $query = [], array|string|null $body = null, array $options = []): ResponseInterface
    {
        return $this->client->send($method, $path, $query, $body, $options);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function resource(string $class): object
    {
        /** @var T */
        return $this->resources[$class] ??= new $class($this->client);
    }
}
