<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\RustFs\Facades\RustFs;
use Siberfx\RustFs\RustFsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RustFsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['RustFs' => RustFs::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('rustfs.connections.default', [
            'endpoint' => 'http://rustfs.test:9000',
            'key' => 'rustfsadmin',
            'secret' => 'rustfssecret',
            'region' => 'us-east-1',
            'bucket' => 'media',
        ]);
        $app['config']->set('rustfs.connections.backup', [
            'endpoint' => 'https://backup.example.com',
            'key' => 'backup-key',
            'secret' => 'backup-secret',
            'region' => 'eu-central-1',
            'bucket' => 'archive',
        ]);
        $app['config']->set('filesystems.disks.rustfs', ['driver' => 'rustfs']);
        $app['config']->set('filesystems.disks.backups', ['driver' => 'rustfs', 'connection' => 'backup', 'root' => 'nightly', 'bucket' => 'dumps']);
    }
}
