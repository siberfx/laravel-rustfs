<?php

declare(strict_types=1);

namespace Siberfx\RustFs;

use Illuminate\Contracts\Config\Repository;
use Siberfx\RustFs\Exceptions\RustFsException;

/**
 * Resolves named RustFS connections from config/rustfs.php.
 * Calls not defined here are forwarded to the default connection.
 *
 * @mixin RustFsConnection
 */
class RustFsManager
{
    /** @var array<string, RustFsConnection> */
    private array $connections = [];

    public function __construct(private readonly Repository $config) {}

    public function connection(?string $name = null): RustFsConnection
    {
        $name ??= $this->getDefaultConnection();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    public function getDefaultConnection(): string
    {
        return (string) $this->config->get('rustfs.default', 'default');
    }

    /**
     * Register a ready-made connection (handy in tests).
     */
    public function setConnection(string $name, RustFsConnection $connection): void
    {
        $this->connections[$name] = $connection;
    }

    public function forget(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];

            return;
        }

        unset($this->connections[$name]);
    }

    /**
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->{$method}(...$arguments);
    }

    private function resolve(string $name): RustFsConnection
    {
        $config = $this->config->get("rustfs.connections.{$name}");
        if (! is_array($config)) {
            throw new RustFsException("RustFS connection [{$name}] is not configured.");
        }

        return RustFsConnection::make($config);
    }
}
