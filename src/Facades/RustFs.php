<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Facades;

use Illuminate\Support\Facades\Facade;
use Siberfx\RustFs\RustFsManager;

/**
 * @method static \Siberfx\RustFs\RustFsConnection connection(?string $name = null)
 * @method static string getDefaultConnection()
 * @method static void setConnection(string $name, \Siberfx\RustFs\RustFsConnection $connection)
 * @method static void forget(?string $name = null)
 * @method static \Siberfx\RustFs\ConnectionConfig config()
 * @method static \Aws\S3\S3Client s3()
 * @method static \Siberfx\RustFs\S3\Buckets buckets()
 * @method static \Siberfx\RustFs\S3\Objects objects(?string $bucket = null)
 * @method static \Siberfx\RustFs\S3\Objects bucket(?string $bucket = null)
 * @method static \Siberfx\RustFs\Admin\Admin admin()
 * @method static \Siberfx\RustFs\Health\Health health()
 *
 * @see RustFsManager
 */
class RustFs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RustFsManager::class;
    }
}
