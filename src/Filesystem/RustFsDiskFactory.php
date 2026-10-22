<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Filesystem;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemManager;
use Siberfx\RustFs\ConnectionConfig;
use Siberfx\RustFs\Exceptions\RustFsException;

/**
 * Builds the "rustfs" disk on top of Laravel's S3 driver. Every option the disk omits falls back to
 * the RustFS connection named by "connection" (default connection otherwise):
 *
 *     'media' => ['driver' => 'rustfs', 'bucket' => 'media', 'visibility' => 'public'],
 */
final class RustFsDiskFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function create(FilesystemManager $manager, Repository $appConfig, array $config): RustFsFilesystemAdapter
    {
        $config = self::resolveConfig($appConfig, $config);

        $base = $manager->createS3Driver($config);
        if (! $base instanceof AwsS3V3Adapter) {
            throw new RustFsException('Unexpected adapter returned by the S3 driver.');
        }

        return new RustFsFilesystemAdapter($base->getDriver(), $base->getAdapter(), $config, $base->getClient());
    }

    /**
     * Merge the disk options over the connection settings, in the format expected by Laravel's s3 driver.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function resolveConfig(Repository $appConfig, array $config): array
    {
        $name = (string) ($config['connection'] ?? $appConfig->get('rustfs.default', 'default'));
        $connection = (array) $appConfig->get("rustfs.connections.{$name}", []);

        $merged = array_filter($config, static fn (mixed $value) => $value !== null) + $connection;
        $resolved = ConnectionConfig::fromArray($merged);

        if ($resolved->bucket === null) {
            throw new RustFsException('The rustfs disk needs a "bucket" (on the disk or on its RustFS connection).');
        }

        $options = $resolved->s3ClientOptions();
        unset($options['credentials']);

        return array_merge($options, [
            'key' => $resolved->key,
            'secret' => $resolved->secret,
            'token' => $resolved->token,
            'bucket' => $resolved->bucket,
            // Public URLs default to path-style URLs on the endpoint
            'url' => $config['url'] ?? ($resolved->endpoint . '/' . $resolved->bucket),
        ], array_diff_key($config, array_flip(['connection', 'key', 'secret', 'token', 'bucket', 'url', 'endpoint', 'region', 'use_path_style_endpoint', 'http'])));
    }
}
