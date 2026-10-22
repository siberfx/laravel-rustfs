<?php

declare(strict_types=1);

namespace Siberfx\RustFs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\ServiceProvider;
use Siberfx\RustFs\Admin\Admin;
use Siberfx\RustFs\Console\BucketCommand;
use Siberfx\RustFs\Console\HealthCommand;
use Siberfx\RustFs\Console\InfoCommand;
use Siberfx\RustFs\Console\UserCommand;
use Siberfx\RustFs\Filesystem\RustFsDiskFactory;

class RustFsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/rustfs.php', 'rustfs');

        $this->app->singleton(RustFsManager::class, fn (Container $app) => new RustFsManager($app['config']));
        $this->app->alias(RustFsManager::class, 'rustfs');

        $this->app->bind(RustFsConnection::class, fn (Container $app) => $app->make(RustFsManager::class)->connection());
        $this->app->bind(Admin::class, fn (Container $app) => $app->make(RustFsManager::class)->connection()->admin());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/rustfs.php' => $this->app->configPath('rustfs.php'),
            ], 'rustfs-config');

            $this->commands([
                HealthCommand::class,
                InfoCommand::class,
                BucketCommand::class,
                UserCommand::class,
            ]);
        }

        $this->callAfterResolving('filesystem', function (FilesystemManager $manager, Container $app) {
            // Laravel binds this callback to the manager, so it must not rely on $this
            $manager->extend('rustfs', fn (Container $app, array $config) => RustFsDiskFactory::create($app['filesystem'], $app['config'], $config));
        });
    }
}
