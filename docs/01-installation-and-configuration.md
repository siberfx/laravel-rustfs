# 1. Installation & configuration

## Requirements

| | Version |
| --- | --- |
| PHP | 8.4.27 or newer (8.5 supported) |
| Laravel | 13.x (`illuminate/*` ^13.0) |
| RustFS | Built and tested against 1.0.x |
| PHP extensions | `json`, plus whatever the AWS SDK needs (`curl` is recommended) |

These are installed with the package: `aws/aws-sdk-php` ^3.400, `league/flysystem-aws-s3-v3` ^3.35 and `guzzlehttp/guzzle` ^7.9 | ^8.0.

## Install

```bash
composer require siberfx/laravel-rustfs
```

Package discovery registers `Siberfx\RustFs\RustFsServiceProvider` and the `RustFs` facade alias for you. To edit the configuration, publish it:

```bash
php artisan vendor:publish --tag=rustfs-config
```

This creates `config/rustfs.php`.

## Environment variables

```dotenv
RUSTFS_ENDPOINT=http://127.0.0.1:9000
RUSTFS_ACCESS_KEY=rustfsadmin
RUSTFS_SECRET_KEY=rustfsadmin
RUSTFS_REGION=us-east-1
RUSTFS_BUCKET=media
```

| Variable | Default | Meaning |
| --- | --- | --- |
| `RUSTFS_CONNECTION` | `default` | Connection used when no name is given |
| `RUSTFS_ENDPOINT` | `http://127.0.0.1:9000` | Base URL of the RustFS S3/API port (not the console port) |
| `RUSTFS_ACCESS_KEY` | `rustfsadmin` | Access key: root user, IAM user or service account |
| `RUSTFS_SECRET_KEY` | `rustfsadmin` | Secret key |
| `RUSTFS_SESSION_TOKEN` | – | Session token for temporary (STS) credentials |
| `RUSTFS_REGION` | `us-east-1` | Region used to sign requests. It must match the server's region if you set one there |
| `RUSTFS_BUCKET` | – | Default bucket for `RustFs::objects()` and for disks that don't set one |
| `RUSTFS_PATH_STYLE` | `true` | Path-style addressing (`host/bucket/key`). Leave it on unless you have configured `--server-domains` |
| `RUSTFS_ADMIN_PREFIX` | `/rustfs/admin` | Admin API base path (see below) |
| `RUSTFS_ADMIN_RETRIES` | `3` | How many times to retry admin calls that get `503 Service Unavailable` |
| `RUSTFS_ADMIN_RETRY_DELAY_MS` | `1000` | Base delay for those retries. The wait grows linearly and respects `Retry-After` |
| `RUSTFS_TIMEOUT` | `30` | Guzzle request timeout, in seconds |
| `RUSTFS_CONNECT_TIMEOUT` | `5` | Guzzle connect timeout, in seconds |
| `RUSTFS_VERIFY_TLS` | `true` | Verify TLS certificates. Only disable this for local self-signed setups |

> **Change the default credentials in production.** RustFS starts with `rustfsadmin` / `rustfsadmin` unless you pass `--access-key` / `--secret-key` (or `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY`) to the server.

## The config file

```php
return [
    'default' => env('RUSTFS_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'endpoint' => env('RUSTFS_ENDPOINT', 'http://127.0.0.1:9000'),
            'key' => env('RUSTFS_ACCESS_KEY', 'rustfsadmin'),
            'secret' => env('RUSTFS_SECRET_KEY', 'rustfsadmin'),
            'token' => env('RUSTFS_SESSION_TOKEN'),
            'region' => env('RUSTFS_REGION', 'us-east-1'),
            'bucket' => env('RUSTFS_BUCKET'),
            'use_path_style_endpoint' => (bool) env('RUSTFS_PATH_STYLE', true),
            'admin_prefix' => env('RUSTFS_ADMIN_PREFIX', '/rustfs/admin'),
            'admin_retries' => (int) env('RUSTFS_ADMIN_RETRIES', 3),
            'admin_retry_delay_ms' => (int) env('RUSTFS_ADMIN_RETRY_DELAY_MS', 1000),
            'http' => [
                'timeout' => (float) env('RUSTFS_TIMEOUT', 30),
                'connect_timeout' => (float) env('RUSTFS_CONNECT_TIMEOUT', 5),
                'verify' => (bool) env('RUSTFS_VERIFY_TLS', true),
            ],
        ],
    ],
];
```

The `http` array accepts any [Guzzle request option](https://docs.guzzlephp.org/en/stable/request-options.html), for example `proxy`, `cert` or `headers`. It is used for both S3 and Admin calls.

### Why `/rustfs/admin` and not `/minio/admin`?

RustFS serves its Admin API under two prefixes:

- `/rustfs/admin/...` takes and returns **plain JSON**. This package uses it.
- `/minio/admin/...` exists for `mc` / `madmin` compatibility, and **encrypts** request and response bodies with the caller's secret key.

Keep the default. If you point `admin_prefix` at `/minio/admin`, endpoints that carry bodies will fail, because this package does not encrypt them the madmin way.

## Multiple connections

Add as many connections as you need:

```php
'connections' => [
    'default' => [ /* ... */ ],

    'backup' => [
        'endpoint' => env('RUSTFS_BACKUP_ENDPOINT'),
        'key' => env('RUSTFS_BACKUP_KEY'),
        'secret' => env('RUSTFS_BACKUP_SECRET'),
        'region' => 'eu-central-1',
        'bucket' => 'archive',
    ],
],
```

```php
use Siberfx\RustFs\Facades\RustFs;

RustFs::buckets()->list();                      // default connection
RustFs::connection('backup')->buckets()->list();
RustFs::connection('backup')->admin()->system()->info();
```

Each connection is created on first use and reused after that. `RustFs::forget('backup')` drops a cached connection, and `RustFs::forget()` drops all of them. That is useful after changing config at runtime.

## Using the classes without the facade

Everything is resolvable from the container:

| Type-hint | Resolves to |
| --- | --- |
| `Siberfx\RustFs\RustFsManager` (alias `rustfs`) | The manager (singleton) |
| `Siberfx\RustFs\RustFsConnection` | The default connection |
| `Siberfx\RustFs\Admin\Admin` | The default connection's Admin API |

```php
use Siberfx\RustFs\RustFsManager;

final class ArchiveReport
{
    public function __construct(private RustFsManager $rustfs) {}

    public function __invoke(): array
    {
        return $this->rustfs->connection('backup')->admin()->system()->dataUsageInfo();
    }
}
```

You can also build a connection outside Laravel's config:

```php
use Siberfx\RustFs\RustFsConnection;

$rustfs = RustFsConnection::make([
    'endpoint' => 'https://s3.internal:9000',
    'key' => '...',
    'secret' => '...',
]);
```

## Laravel Herd

Herd Pro has a built-in RustFS service. Its default local settings are:

```dotenv
RUSTFS_ENDPOINT=http://localhost:9000
RUSTFS_ACCESS_KEY=herd
RUSTFS_SECRET_KEY=secretkey
RUSTFS_REGION=us-east-1
```

The web console is at `http://localhost:9001`. Run `php artisan rustfs:health` to check the connection.

## Running RustFS yourself

```bash
# Docker
docker run -d --name rustfs -p 9000:9000 -p 9001:9001 \
  -e RUSTFS_ACCESS_KEY=rustfsadmin -e RUSTFS_SECRET_KEY=change-me \
  -v rustfs-data:/data rustfs/rustfs:1.0.3

# Native binary (Linux/macOS/Windows builds are on the GitHub releases page)
rustfs server --address 0.0.0.0:9000 --access-key rustfsadmin --secret-key change-me /data
```

Next: [the filesystem disk](02-filesystem-disk.md).
