# RustFS for Laravel

[![tests](https://github.com/siberfx/laravel-rustfs/actions/workflows/tests.yml/badge.svg)](https://github.com/siberfx/laravel-rustfs/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/siberfx/laravel-rustfs.svg)](https://packagist.org/packages/siberfx/laravel-rustfs)
[![PHP](https://img.shields.io/badge/php-%5E8.4.27-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-13.x-ff2d20.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

`siberfx/laravel-rustfs` brings [RustFS](https://github.com/rustfs/rustfs), the high-performance S3-compatible object store written in Rust, to Laravel 13+. It covers:

- **Storage**: a `rustfs` filesystem disk, so `Storage::disk('media')` works like any S3 disk, with RustFS extras on top.
- **The full S3 feature set**: versioning, object lock (WORM), lifecycle/ILM, replication, SSE-S3/SSE-KMS encryption, bucket policies, tags, CORS, event notifications, multipart uploads, presigned URLs and POST forms, S3 Select.
- **The RustFS Admin API**: users, groups, policies, service accounts, MFA, quotas, tiers, pools, healing, KMS, site replication, server config, notification/audit targets, diagnostics, tracing and profiling. That is 21 resource groups and over 200 methods, all SigV4-signed.
- **Artisan commands** for health checks, server info, buckets and users.

It is tested against a real RustFS server: the live test suite runs in CI against `rustfs/rustfs`, and was verified locally against the RustFS service in Laravel Herd.

```php
use Illuminate\Support\Facades\Storage;
use Siberfx\RustFs\Facades\RustFs;

Storage::disk('media')->put('avatars/42.png', $png);
Storage::disk('media')->temporaryUrl('avatars/42.png', now()->addMinutes(5));

RustFs::buckets()->create('invoices', objectLock: true);
RustFs::buckets()->putObjectLock('invoices', 'COMPLIANCE', years: 7);
RustFs::objects('invoices')->upload('2026/10.pdf', storage_path('invoices/10.pdf'));

RustFs::admin()->users()->create('alice', 'a-long-secret-key', policy: 'readwrite');
RustFs::admin()->quotas()->set('media', 50 * 1024 ** 3);
```

## Requirements

- PHP **8.4.27+** (8.5 supported)
- Laravel **13.x**
- RustFS **1.0.x**

## Installation

```bash
composer require siberfx/laravel-rustfs
php artisan vendor:publish --tag=rustfs-config   # optional
```

```dotenv
RUSTFS_ENDPOINT=http://127.0.0.1:9000
RUSTFS_ACCESS_KEY=rustfsadmin
RUSTFS_SECRET_KEY=rustfsadmin
RUSTFS_REGION=us-east-1
RUSTFS_BUCKET=media
```

> **Laravel Herd** users: Herd Pro's RustFS service runs at `http://localhost:9000` with `herd` / `secretkey`.

```bash
php artisan rustfs:health
```

## Quick tour

### Filesystem disk

```php
// config/filesystems.php
'disks' => [
    'media'   => ['driver' => 'rustfs', 'bucket' => 'media'],
    'backups' => ['driver' => 'rustfs', 'connection' => 'backup', 'root' => 'nightly'],
],
```

Any option a disk omits comes from its RustFS connection. The disk is a `RustFsFilesystemAdapter` and adds `objects()`, `buckets()`, `key()`, `setTags()`, `getTags()` and `versions()` to everything an S3 disk already does.

> RustFS ignores object ACLs, so `'visibility' => 'public'` does **not** make files anonymously readable. Use a bucket policy instead: `RustFs::buckets()->makePublic('media', 'public/')`.

### Buckets & objects

```php
$buckets = RustFs::buckets();
$buckets->create('photos', versioning: true);
$buckets->putLifecycle('photos', [['ID' => 'tmp', 'Status' => 'Enabled', 'Filter' => ['Prefix' => 'tmp/'], 'Expiration' => ['Days' => 1]]]);
$buckets->putEncryption('photos', 'aws:kms', $keyId);
$buckets->putCors('photos', [['AllowedMethods' => ['GET', 'PUT'], 'AllowedOrigins' => ['https://app.example.com']]]);

$objects = RustFs::objects('photos');
$objects->put('a.jpg', $bytes, ['ContentType' => 'image/jpeg']);
foreach ($objects->list('2026/') as $object) { /* lazily paginated */ }
$objects->putLegalHold('evidence.zip', true);
$objects->temporaryUploadUrl('incoming/b.jpg', '+5 minutes');
$rows = $objects->selectRows('sales.csv', "SELECT * FROM S3Object s WHERE s.country = 'TR'");
```

### Admin API

```php
$admin = RustFs::admin();

$admin->users()->create('alice', 'secret', policy: 'readwrite');
$admin->groups()->addMembers('developers', ['alice']);
$admin->serviceAccounts()->create(targetUser: 'alice', name: 'ci', expiration: now()->addMonth());
$admin->system()->info();
$admin->heal()->start('photos', options: ['recursive' => true]);
$admin->kms()->createKey('app key');
$admin->notifications()->set('webhook', 'primary', ['endpoint' => 'https://...', 'queue_dir' => '/var/lib/rustfs/events']);
$admin->config()->set('scanner idle_mode=false');

$admin->request('GET', '/v4/heal/replacement-recovery');   // any route without a wrapper
```

### Artisan

```bash
php artisan rustfs:health
php artisan rustfs:info
php artisan rustfs:bucket create invoices --lock
php artisan rustfs:user create alice --policy=readwrite
```

## Documentation

The full guide is in [`docs/`](docs/README.md):

1. [Installation & configuration](docs/01-installation-and-configuration.md): every config key, multiple connections, Herd, Docker
2. [Filesystem disk](docs/02-filesystem-disk.md): disk options, public files, temporary URLs, RustFS extras
3. [Buckets](docs/03-buckets.md): versioning, object lock, lifecycle, replication, encryption, policies, tags, CORS, notifications
4. [Objects](docs/04-objects.md): uploads, multipart, listing, versions, retention, legal hold, presigning, S3 Select
5. [Admin: identity & access](docs/05-admin-iam.md): users, groups, policies, service accounts, MFA, IAM export
6. [Admin: cluster operations](docs/06-admin-cluster.md): info, quotas, tiers, pools, healing, config, diagnostics, tracing
7. [Admin: security, replication & events](docs/07-admin-security-replication.md): KMS, replication, site replication, targets, ILM
8. [Artisan commands](docs/08-artisan-commands.md)
9. [Errors, testing & troubleshooting](docs/09-testing-and-troubleshooting.md)
10. [Admin API route map](docs/admin-route-map.md): every method and the RustFS endpoint it calls

## RustFS behaviours this package handles

These were found while testing against a real server:

| RustFS behaviour | What the package does |
| --- | --- |
| `add-user` ignores its `policy` field | `users()->create(..., policy:)` attaches the policy with a second call |
| `/v3/info` and `/v3/storageinfo` wrap their data in an `{"info": ...}` envelope | `info()` / `storageInfo()` unwrap it (`rawInfo()` keeps everything) |
| Quota/usage calls answer `503` until the scanner has measured a new bucket | 503 responses are retried with backoff (configurable) |
| Notification target types are sub-system names (`notify_webhook`) | Pass `'webhook'`; the `notify_`/`audit_` prefix is added for you |
| Module switch updates need both flags | `updateModuleSwitches(notify: true)` keeps the flag you didn't pass |
| Object ACLs are accepted but ignored | Documented. Use `makePublic()` / bucket policies |
| `/minio/admin` expects encrypted bodies | The package uses the plain-JSON `/rustfs/admin` prefix |

## Testing

```bash
composer test       # 150+ unit and feature tests, no server needed
composer analyse    # PHPStan level 6
composer format     # Pint

# End-to-end against a running RustFS (here: Laravel Herd)
RUSTFS_LIVE_ENDPOINT=http://127.0.0.1:9000 RUSTFS_LIVE_KEY=herd RUSTFS_LIVE_SECRET=secretkey vendor/bin/pest --group=live
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE).
