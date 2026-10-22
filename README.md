# RustFS for Laravel

[![tests](https://github.com/siberfx/laravel-rustfs/actions/workflows/tests.yml/badge.svg)](https://github.com/siberfx/laravel-rustfs/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/siberfx/laravel-rustfs.svg)](https://packagist.org/packages/siberfx/laravel-rustfs)
[![PHP](https://img.shields.io/badge/php-%5E8.4.27-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-13.x-ff2d20.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Use [RustFS](https://github.com/rustfs/rustfs), the high-performance S3-compatible object store written in Rust, from Laravel 13+. The package gives you:

- a **`rustfs` filesystem disk** for `Storage::disk(...)`,
- the **S3 feature set** RustFS supports: versioning, object lock (WORM), lifecycle, replication, encryption, policies, tags, CORS, event notifications, multipart uploads, presigned URLs and forms, and S3 Select,
- the **RustFS Admin API**: IAM, service accounts, quotas, tiers, pools, healing, KMS, site replication, config, notification and audit targets, diagnostics, tracing,
- **Artisan commands** for health checks, server info, buckets and users.

Every sample in this README uses the real API, and the behaviour it describes is covered by the [live test suite](#testing) running against a real RustFS server.

---

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Files with the `rustfs` disk](#files-with-the-rustfs-disk)
- [Recipes](#recipes)
  - [Upload a file from a form](#upload-a-file-from-a-form)
  - [Private downloads with expiring links](#private-downloads-with-expiring-links)
  - [Direct browser uploads (presigned PUT)](#direct-browser-uploads-presigned-put)
  - [HTML form uploads with server-side limits (presigned POST)](#html-form-uploads-with-server-side-limits-presigned-post)
  - [Large and resumable uploads (multipart)](#large-and-resumable-uploads-multipart)
  - [Serve a public folder](#serve-a-public-folder)
  - [Keep every version of a document](#keep-every-version-of-a-document)
  - [Immutable records with object lock (WORM)](#immutable-records-with-object-lock-worm)
  - [Expire temp files and archive old data (lifecycle)](#expire-temp-files-and-archive-old-data-lifecycle)
  - [Encrypt everything at rest (KMS)](#encrypt-everything-at-rest-kms)
  - [React to uploads (event notifications)](#react-to-uploads-event-notifications)
  - [Query CSV/JSON without downloading it (S3 Select)](#query-csvjson-without-downloading-it-s3-select)
  - [Tag objects and buckets](#tag-objects-and-buckets)
  - [Copy, move and bulk-delete](#copy-move-and-bulk-delete)
  - [Allow your SPA to talk to RustFS (CORS)](#allow-your-spa-to-talk-to-rustfs-cors)
  - [Replicate a bucket to a DR site](#replicate-a-bucket-to-a-dr-site)
- [Administration](#administration)
  - [Provision a tenant: bucket, user, policy, quota](#provision-a-tenant-bucket-user-policy-quota)
  - [Service accounts for apps and CI](#service-accounts-for-apps-and-ci)
  - [Groups](#groups)
  - [Cluster status, usage and healing](#cluster-status-usage-and-healing)
  - [Server configuration](#server-configuration)
  - [Backups of IAM and bucket metadata](#backups-of-iam-and-bucket-metadata)
  - [Live request tracing](#live-request-tracing)
  - [Anything else: raw admin requests](#anything-else-raw-admin-requests)
- [Health checks](#health-checks)
- [Artisan commands](#artisan-commands)
- [Multiple RustFS deployments](#multiple-rustfs-deployments)
- [Error handling](#error-handling)
- [Testing your application](#testing-your-application)
- [RustFS behaviours this package handles](#rustfs-behaviours-this-package-handles)
- [Testing](#testing)
- [Documentation](#documentation)

---

## Installation

Requirements: **PHP 8.4.27+**, **Laravel 13**, **RustFS 1.0.x**.

```bash
composer require siberfx/laravel-rustfs
php artisan vendor:publish --tag=rustfs-config   # optional: config/rustfs.php
```

The service provider and the `RustFs` facade are auto-discovered.

**Need a RustFS server?**

```bash
# Laravel Herd Pro: enable the RustFS service. It runs at localhost:9000 with herd / secretkey

# Docker
docker run -d --name rustfs -p 9000:9000 -p 9001:9001 \
  -e RUSTFS_ACCESS_KEY=rustfsadmin -e RUSTFS_SECRET_KEY=change-me \
  -v rustfs-data:/data rustfs/rustfs:1.0.3
```

Port `9000` is the S3/Admin API. Port `9001` is the web console.

## Configuration

```dotenv
RUSTFS_ENDPOINT=http://127.0.0.1:9000
RUSTFS_ACCESS_KEY=rustfsadmin
RUSTFS_SECRET_KEY=change-me
RUSTFS_REGION=us-east-1
RUSTFS_BUCKET=media
```

Verify the connection:

```bash
php artisan rustfs:health
```

| Variable | Default | Purpose |
| --- | --- | --- |
| `RUSTFS_ENDPOINT` | `http://127.0.0.1:9000` | API URL (not the console port) |
| `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY` | `rustfsadmin` | Credentials. Admin calls need admin rights |
| `RUSTFS_SESSION_TOKEN` | – | For temporary STS credentials |
| `RUSTFS_REGION` | `us-east-1` | Signing region. Must match the server |
| `RUSTFS_BUCKET` | – | Default bucket for `RustFs::objects()` and disks |
| `RUSTFS_PATH_STYLE` | `true` | `host/bucket/key` addressing |
| `RUSTFS_ADMIN_PREFIX` | `/rustfs/admin` | Plain-JSON admin API prefix |
| `RUSTFS_ADMIN_RETRIES` / `RUSTFS_ADMIN_RETRY_DELAY_MS` | `3` / `1000` | Retries for admin `503` responses |
| `RUSTFS_TIMEOUT` / `RUSTFS_CONNECT_TIMEOUT` / `RUSTFS_VERIFY_TLS` | `30` / `5` / `true` | HTTP options |

Every option is explained in [docs/01-installation-and-configuration.md](docs/01-installation-and-configuration.md).

---

## Files with the `rustfs` disk

Add disks to `config/filesystems.php`. Anything a disk leaves out is taken from its RustFS connection:

```php
'disks' => [
    'rustfs'  => ['driver' => 'rustfs'],                                  // uses RUSTFS_BUCKET
    'media'   => ['driver' => 'rustfs', 'bucket' => 'media'],
    'backups' => ['driver' => 'rustfs', 'bucket' => 'backups', 'root' => 'nightly', 'throw' => true],
    'assets'  => ['driver' => 'rustfs', 'bucket' => 'assets', 'url' => 'https://cdn.example.com'],
],
```

Optionally make it the default in `.env`:

```dotenv
FILESYSTEM_DISK=rustfs
```

The full Laravel filesystem API works:

```php
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('media');

$disk->put('notes/hello.txt', 'Hello RustFS');
$disk->writeStream('videos/intro.mp4', fopen('/path/intro.mp4', 'rb'));   // streamed, low memory

$disk->exists('notes/hello.txt');          // true
$disk->get('notes/hello.txt');             // "Hello RustFS"
$disk->size('notes/hello.txt');            // 12
$disk->lastModified('notes/hello.txt');    // unix timestamp
$disk->mimeType('notes/hello.txt');        // "text/plain"

$disk->files('notes');                     // ['notes/hello.txt']
$disk->allFiles();                         // recursive
$disk->copy('notes/hello.txt', 'notes/copy.txt');
$disk->move('notes/copy.txt', 'archive/copy.txt');
$disk->delete(['notes/hello.txt', 'archive/copy.txt']);
$disk->deleteDirectory('tmp');

$disk->temporaryUrl('notes/hello.txt', now()->addMinutes(10));   // signed download link
```

The disk also exposes RustFS extras:

```php
$disk = Storage::disk('backups');                 // root = "nightly"

$disk->setTags('db.sql.gz', ['retention' => '30d']);
$disk->getTags('db.sql.gz');                      // ['retention' => '30d']

foreach ($disk->versions('db.sql.gz') as $version) {   // needs versioning on the bucket
    echo $version['VersionId'], PHP_EOL;
}

$disk->key('db.sql.gz');                          // "nightly/db.sql.gz", the real object key
$disk->objects();                                 // full object API for the disk's bucket
$disk->buckets();                                 // bucket API
$disk->getClient();                               // Aws\S3\S3Client
```

> `objects()` takes **bucket keys**, so wrap paths with `$disk->key($path)` when the disk has a `root`.

---

## Recipes

All recipes use the `RustFs` facade:

```php
use Siberfx\RustFs\Facades\RustFs;

$buckets = RustFs::buckets();              // bucket-level API
$objects = RustFs::objects('media');       // object API for one bucket (omit the name to use RUSTFS_BUCKET)
$admin   = RustFs::admin();                // Admin API
```

### Upload a file from a form

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController
{
    public function store(Request $request)
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $path = $request->file('avatar')->store("avatars/{$request->user()->id}", 'media');

        $request->user()->update(['avatar_path' => $path]);

        return back();
    }
}
```

Need metadata, a content type or encryption? Use the object API:

```php
RustFs::objects('media')->put("avatars/{$user->id}.png", $png, [
    'ContentType' => 'image/png',
    'CacheControl' => 'max-age=86400',
    'Metadata' => ['user-id' => (string) $user->id],
]);
```

### Private downloads with expiring links

Files stay private. Users get a signed link that expires:

```php
public function download(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    return redirect(Storage::disk('media')->temporaryUrl(
        $invoice->path,
        now()->addMinutes(5),
        ['ResponseContentDisposition' => 'attachment; filename="invoice-' . $invoice->number . '.pdf"'],
    ));
}
```

Or stream through your app without loading the file into memory:

```php
return response()->stream(function () use ($invoice) {
    $stream = RustFs::objects('media')->stream($invoice->path);
    while (! $stream->eof()) {
        echo $stream->read(1024 * 64);
    }
}, 200, ['Content-Type' => 'application/pdf']);
```

### Direct browser uploads (presigned PUT)

The browser uploads straight to RustFS, so your server never touches the bytes:

```php
// routes/api.php
Route::post('/uploads/sign', function (Request $request) {
    $request->validate(['name' => 'required|string', 'type' => 'required|string']);

    $key = 'incoming/' . Str::uuid() . '/' . basename($request->string('name'));

    ['url' => $url] = Storage::disk('media')->temporaryUploadUrl(
        $key,
        now()->addMinutes(10),
        ['ContentType' => $request->string('type')->toString()],
    );

    return ['key' => $key, 'url' => $url];
});
```

```js
const { url, key } = await (await fetch('/api/uploads/sign', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    body: JSON.stringify({ name: file.name, type: file.type }),
})).json();

await fetch(url, { method: 'PUT', headers: { 'Content-Type': file.type }, body: file });
// then tell your API the upload finished, sending `key`
```

The browser's `Content-Type` header is what gets stored as the object's type, so always send it.

A presigned PUT does **not** enforce type or size: on RustFS 1.0.x, an upload with a different `Content-Type` than the one you signed is still accepted. If you need the server to enforce limits, use a [presigned POST](#html-form-uploads-with-server-side-limits-presigned-post), or validate the object (`head()`) before you accept the key. The bucket also needs a CORS rule that allows `PUT` from your origin ([see CORS](#allow-your-spa-to-talk-to-rustfs-cors)).

### HTML form uploads with server-side limits (presigned POST)

RustFS itself enforces the key prefix, size and type:

```php
$form = RustFs::objects('media')->presignedPost(
    formInputs: ['key' => 'uploads/${filename}', 'Content-Type' => 'image/jpeg'],
    conditions: [
        ['starts-with', '$key', 'uploads/'],
        ['content-length-range', 1, 10 * 1024 * 1024],   // 1 byte to 10 MiB
        ['eq', '$Content-Type', 'image/jpeg'],
    ],
    expires: '+30 minutes',
);

return view('upload', ['form' => $form]);
```

```blade
<form action="{{ $form['url'] }}" method="POST" enctype="multipart/form-data">
    @foreach ($form['fields'] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <input type="file" name="file">   {{-- must be the last field --}}
    <button>Upload</button>
</form>
```

### Large and resumable uploads (multipart)

**Server side.** `upload()` switches to a parallel multipart upload above the threshold:

```php
RustFs::objects('backups')->upload(
    'db/2026-10-23.sql.gz',
    storage_path('app/dump.sql.gz'),          // a path, resource, stream or string
    params: ['ContentType' => 'application/gzip'],
    multipartThreshold: 64 * 1024 * 1024,
    partSize: 32 * 1024 * 1024,
    concurrency: 8,
);
```

**Client side (resumable, chunked).** The browser uploads each part to its own presigned URL:

```php
$objects = RustFs::objects('media');

// 1. start
$uploadId = $objects->createMultipartUpload('videos/raw.mov', ['ContentType' => 'video/quicktime']);

// 2. one presigned URL per part. The client PUTs each chunk (at least 5 MiB, except the last)
$urls = collect(range(1, $partCount))->mapWithKeys(fn (int $n) => [
    $n => $objects->temporaryPartUploadUrl('videos/raw.mov', $uploadId, $n, '+2 hours'),
]);

// 3. the client returns the ETag response header of each part
$objects->completeMultipartUpload('videos/raw.mov', $uploadId, [
    ['PartNumber' => 1, 'ETag' => '"etag-1"'],
    ['PartNumber' => 2, 'ETag' => '"etag-2"'],
]);

// To resume: list what has arrived. To give up: abort
$objects->parts('videos/raw.mov', $uploadId);
$objects->abortMultipartUpload('videos/raw.mov', $uploadId);
$objects->multipartUploads('videos/');      // unfinished uploads
```

### Serve a public folder

RustFS **ignores object ACLs**, so `'visibility' => 'public'` does not make files public. Grant anonymous read with a bucket policy:

```php
RustFs::buckets()->makePublic('media', 'public/');   // everything under public/

Storage::disk('media')->put('public/logo.svg', $svg);
Storage::disk('media')->url('public/logo.svg');       // http://127.0.0.1:9000/media/public/logo.svg
```

Custom policy:

```php
RustFs::buckets()->putPolicy('media', [
    'Version' => '2012-10-17',
    'Statement' => [[
        'Effect' => 'Allow',
        'Principal' => ['AWS' => ['*']],
        'Action' => ['s3:GetObject'],
        'Resource' => ['arn:aws:s3:::media/public/*', 'arn:aws:s3:::media/shared/*'],
    ]],
]);

RustFs::buckets()->policy('media');        // the current policy (or null)
RustFs::buckets()->deletePolicy('media');  // private again
```

### Keep every version of a document

```php
$buckets = RustFs::buckets();
$buckets->create('contracts', versioning: true);    // or enableVersioning('contracts') later

$objects = RustFs::objects('contracts');
$v1 = $objects->put('acme.pdf', $draft)['VersionId'];
$v2 = $objects->put('acme.pdf', $final)['VersionId'];

$objects->get('acme.pdf');                   // latest ($final)
$objects->get('acme.pdf', versionId: $v1);   // the draft

foreach ($objects->versions('acme.pdf') as $version) {
    // ['Key', 'VersionId', 'IsLatest', 'LastModified', 'Size', 'IsDeleteMarker' => false]
}

// Restore the draft as the current version
$objects->copy('acme.pdf', 'acme.pdf', sourceVersionId: $v1);

// "Delete" adds a delete marker. Remove a version for good with its id
$objects->delete('acme.pdf');
$objects->delete('acme.pdf', versionId: $v2);
```

### Immutable records with object lock (WORM)

Object lock can only be enabled when the bucket is created:

```php
$buckets = RustFs::buckets();
$buckets->create('audit-records', objectLock: true);

// Default: every new object is locked for 7 years
$buckets->putObjectLock('audit-records', 'COMPLIANCE', years: 7);

$objects = RustFs::objects('audit-records');
$version = $objects->put('2026/10/ledger.csv', $csv)['VersionId'];

$objects->retention('2026/10/ledger.csv', $version);
// ['Mode' => 'COMPLIANCE', 'RetainUntilDate' => DateTime]

// Per-object retention (GOVERNANCE can be bypassed by privileged users, COMPLIANCE cannot)
$objects->putRetention('drafts/a.pdf', 'GOVERNANCE', now()->addDays(30)->toDateTimeImmutable(), $version);
$objects->delete('drafts/a.pdf', $version, bypassGovernanceRetention: true);

// Legal hold: no expiry, stays until it is removed
$objects->putLegalHold('evidence/case-42.zip', true);
$objects->legalHold('evidence/case-42.zip');            // true
$objects->putLegalHold('evidence/case-42.zip', false);
```

### Expire temp files and archive old data (lifecycle)

```php
RustFs::buckets()->putLifecycle('media', [
    [
        'ID' => 'expire-tmp',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => 'tmp/'],
        'Expiration' => ['Days' => 1],
    ],
    [
        'ID' => 'drop-old-versions',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => ''],
        'NoncurrentVersionExpiration' => ['NoncurrentDays' => 30],
    ],
    [
        'ID' => 'abort-stale-uploads',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => ''],
        'AbortIncompleteMultipartUpload' => ['DaysAfterInitiation' => 2],
    ],
    [
        'ID' => 'archive-after-90-days',      // needs a tier, see below
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => 'archive/'],
        'Transitions' => [['Days' => 90, 'StorageClass' => 'COLD']],
    ],
]);

RustFs::buckets()->lifecycle('media');        // the current rules
RustFs::buckets()->deleteLifecycle('media');
```

Register the `COLD` tier (any S3-compatible target) once:

```php
RustFs::admin()->tiers()->add([
    'Version' => 'v1',
    'Type' => 's3',
    'Name' => 'COLD',
    'S3' => [
        'Endpoint' => 'https://cold-storage.example.com',
        'AccessKey' => env('COLD_KEY'),
        'SecretKey' => env('COLD_SECRET'),
        'Bucket' => 'rustfs-cold',
        'Region' => 'us-east-1',
    ],
]);

RustFs::objects('media')->restore('archive/2020.tar', days: 3);   // bring one back temporarily
```

### Encrypt everything at rest (KMS)

```php
$admin = RustFs::admin();

$admin->kms()->serviceStatus();        // backend_type, healthy, ...
$key = $admin->kms()->createKey('Billing data', ['team' => 'finance']);

// Default bucket encryption: every new object is encrypted with this key
RustFs::buckets()->putEncryption('billing', 'aws:kms', $key['key_id']);
// ...or SSE-S3 with server-managed keys
RustFs::buckets()->putEncryption('media', 'AES256');

// Per object
RustFs::objects('billing')->put('inv-1.pdf', $pdf, [
    'ServerSideEncryption' => 'aws:kms',
    'SSEKMSKeyId' => $key['key_id'],
]);

$admin->kms()->rotateKey($key['key_id']);
$admin->kms()->listKeys();
```

KMS must be configured on the server, for example `rustfs server --kms-enable --kms-backend local ...` (or Vault/AWS KMS in production).

### React to uploads (event notifications)

```php
$admin = RustFs::admin();

// 1. Enable the notify module (once per deployment)
$admin->config()->updateModuleSwitches(notify: true);

// 2. Create a webhook target
$admin->notifications()->set('webhook', 'app', [
    'endpoint' => 'https://app.example.com/rustfs/events',
    'auth_token' => 'Bearer ' . config('services.rustfs.webhook_token'),
    'queue_dir' => '/var/lib/rustfs/events',   // absolute path that exists on the RustFS host
    'enable' => true,
]);

// 3. Subscribe a bucket
$arn = $admin->notifications()->arns()[0];      // "arn:rustfs:sqs:us-east-1:app:webhook"
RustFs::buckets()->addNotification('media', $arn, ['s3:ObjectCreated:*'], prefix: 'uploads/', suffix: '.jpg');
```

Receive the events in Laravel:

```php
// routes/api.php
Route::post('/rustfs/events', function (Request $request) {
    abort_unless($request->bearerToken() === config('services.rustfs.webhook_token'), 403);

    foreach ($request->input('Records', []) as $record) {
        ProcessUpload::dispatch(
            $record['s3']['bucket']['name'],
            urldecode($record['s3']['object']['key']),
        );
    }

    return response()->noContent();
});
```

Other target types: `kafka`, `amqp`, `mqtt`, `nats`, `nsq`, `redis`, `mysql`, `postgres`, `pulsar`, `elasticsearch`. RustFS blocks loopback endpoints unless they are allowed with `RUSTFS_OUTBOUND_ALLOW_ORIGINS`. More details are in [docs/03-buckets.md](docs/03-buckets.md#event-notifications).

### Query CSV/JSON without downloading it (S3 Select)

```php
$objects = RustFs::objects('reports');

// CSV with a header row
$rows = $objects->selectRows('sales-2026.csv', "SELECT s.country, s.total FROM S3Object s WHERE s.country = 'TR'");
// [['country' => 'TR', 'total' => '1200'], ...]

// JSON lines, gzip compressed
$errors = $objects->selectRows(
    'logs/2026-10-23.json.gz',
    "SELECT e.message FROM S3Object[*] e WHERE e.level = 'error'",
    input: ['JSON' => ['Type' => 'LINES'], 'CompressionType' => 'GZIP'],
);

// Raw output, here CSV
$csv = $objects->select('sales-2026.csv', 'SELECT * FROM S3Object s LIMIT 100', output: ['CSV' => []]);
```

### Tag objects and buckets

```php
RustFs::objects('media')->putTags('docs/plan.pdf', ['classification' => 'internal', 'owner' => 'alice']);
RustFs::objects('media')->tags('docs/plan.pdf');         // ['classification' => 'internal', 'owner' => 'alice']
RustFs::objects('media')->deleteTags('docs/plan.pdf');

RustFs::buckets()->putTags('media', ['cost-center' => '42']);
RustFs::buckets()->tags('media');
```

Tags can drive lifecycle filters (`'Filter' => ['Tag' => ['Key' => 'temp', 'Value' => 'true']]`).

### Copy, move and bulk-delete

```php
$objects = RustFs::objects('media');

$objects->copy('a.jpg', 'b.jpg');                                     // same bucket
$objects->copy('incoming/a.jpg', 'photos/a.jpg', sourceBucket: 'inbox');   // across buckets
$objects->copy('a.jpg', 'a.jpg', options: ['MetadataDirective' => 'REPLACE', 'ContentType' => 'image/jpeg']);

// Lazily list millions of keys
foreach ($objects->list('photos/2026/') as $object) {
    // ['Key' => ..., 'Size' => ..., 'LastModified' => ..., 'ETag' => ...]
}
foreach ($objects->list('photos/', recursive: false) as $entry) {
    // sub-folders come back as ['Prefix' => 'photos/2026/', 'IsDirectory' => true]
}

$errors = $objects->deleteMany(['a.jpg', 'b.jpg', ['Key' => 'c.jpg', 'VersionId' => 'v1']]);   // batches of 1000
$count = $objects->deletePrefix('tmp/');

RustFs::buckets()->empty('scratch');                  // every version and delete marker
RustFs::buckets()->delete('scratch', force: true);    // empty, then delete
```

### Allow your SPA to talk to RustFS (CORS)

```php
RustFs::buckets()->putCors('media', [[
    'AllowedOrigins' => ['https://app.example.com'],
    'AllowedMethods' => ['GET', 'PUT', 'POST'],
    'AllowedHeaders' => ['*'],
    'ExposeHeaders' => ['ETag'],
    'MaxAgeSeconds' => 3600,
]]);
```

### Replicate a bucket to a DR site

```php
$admin = RustFs::admin();

// Both buckets need versioning
RustFs::buckets()->enableVersioning('photos');

$arn = $admin->replication()->setRemoteTarget('photos', [
    'endpoint' => 'dr.example.com:9000',
    'secure' => true,
    'targetbucket' => 'photos',
    'credentials' => ['accessKey' => env('DR_KEY'), 'secretKey' => env('DR_SECRET')],
    'type' => 'replication',
]);

RustFs::buckets()->putReplication('photos', [
    'Rules' => [[
        'ID' => 'to-dr',
        'Status' => 'Enabled',
        'Priority' => 1,
        'Filter' => ['Prefix' => ''],
        'DeleteMarkerReplication' => ['Status' => 'Enabled'],
        'Destination' => ['Bucket' => $arn],
    ]],
]);

$admin->replication()->metrics('photos');   // replicated / pending / failed
```

To mirror *everything* (all buckets, IAM, policies) between deployments, use `$admin->siteReplication()->add([...])`. See [docs/07](docs/07-admin-security-replication.md#site-replication).

---

## Administration

Admin calls use the connection's credentials, which must have admin rights. Check with `RustFs::admin()->account()->isAdmin()`.

### Provision a tenant: bucket, user, policy, quota

```php
namespace App\Actions;

use Illuminate\Support\Str;
use Siberfx\RustFs\Facades\RustFs;

class ProvisionTenantStorage
{
    /** @return array{bucket: string, accessKey: string, secretKey: string} */
    public function __invoke(string $tenant, int $quotaGb = 10): array
    {
        $bucket = "tenant-{$tenant}";
        $admin = RustFs::admin();

        RustFs::buckets()->create($bucket, versioning: true);

        $admin->policies()->create("{$bucket}-rw", [
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Action' => ['s3:*'],
                'Resource' => ["arn:aws:s3:::{$bucket}", "arn:aws:s3:::{$bucket}/*"],
            ]],
        ]);

        $secret = Str::password(40, symbols: false);
        $admin->users()->create($bucket, $secret, policy: "{$bucket}-rw");

        $admin->quotas()->set($bucket, $quotaGb * 1024 ** 3);

        return ['bucket' => $bucket, 'accessKey' => $bucket, 'secretKey' => $secret];
    }
}
```

Managing users afterwards:

```php
$users = RustFs::admin()->users();

$users->list();                          // ['tenant-acme' => ['status' => 'enabled', 'policyName' => 'tenant-acme-rw', ...]]
$users->info('tenant-acme');
$users->disable('tenant-acme');          // suspend access
$users->enable('tenant-acme');
$users->setSecretKey('tenant-acme', Str::password(40, symbols: false));   // rotate the secret
$users->setPolicy('tenant-acme', 'readonly');
$users->delete('tenant-acme');

RustFs::admin()->quotas()->stats('tenant-acme');   // quota_limit, current_usage, remaining_quota, usage_percentage
RustFs::admin()->quotas()->check('tenant-acme', 500 * 1024 ** 2);   // ['allowed' => true, ...]
```

### Service accounts for apps and CI

Service accounts are extra keys that belong to a user. They can be narrowed with a policy and set to expire:

```php
$credentials = RustFs::admin()->serviceAccounts()->create(
    targetUser: 'tenant-acme',
    name: 'github-actions',
    description: 'Deploys release assets',
    expiration: now()->addMonths(3),
    policy: [
        'Version' => '2012-10-17',
        'Statement' => [['Effect' => 'Allow', 'Action' => ['s3:PutObject'], 'Resource' => ['arn:aws:s3:::tenant-acme/releases/*']]],
    ],
);
// ['accessKey' => '...', 'secretKey' => '...']. The secret is shown only once

$accounts = RustFs::admin()->serviceAccounts();
$accounts->list('tenant-acme');
$accounts->disable($credentials['accessKey']);
$accounts->update($credentials['accessKey'], newExpiration: now()->addYear());
$accounts->delete($credentials['accessKey']);
```

### Groups

```php
$groups = RustFs::admin()->groups();

$groups->create('editors', ['alice', 'bob']);
$groups->setPolicy('editors', 'readwrite');
$groups->addMembers('editors', ['carol']);
$groups->info('editors');                    // ['name', 'status', 'members', 'policy', ...]
$groups->removeMembers('editors', ['alice', 'bob', 'carol']);
$groups->delete('editors');                  // only empty groups can be deleted

// Add or remove policies without replacing the existing ones
RustFs::admin()->policies()->attach(['diagnostics'], group: 'editors');
RustFs::admin()->policies()->detach(['diagnostics'], group: 'editors');
```

### Cluster status, usage and healing

```php
$admin = RustFs::admin();

$info = $admin->system()->info();
$info['mode'];                         // "online"
$info['servers'][0]['version'];
$info['buckets']['count'];

$admin->system()->storageInfo();       // drives, capacity, state
$admin->system()->dataUsageInfo();     // objects, sizes, per-bucket usage (from the scanner)
$admin->pools()->list();

$admin->heal()->start('media', 'photos/', ['recursive' => true]);   // repair a prefix
$admin->heal()->backgroundStatus();

$admin->diagnostics()->scannerStatus();
$admin->diagnostics()->topLocks();
```

### Server configuration

```php
$config = RustFs::admin()->config();

$config->help();                                   // sub-systems: storage_class, scanner, heal, notify_*, audit_*, ...
$config->get('scanner');                           // 'scanner cycle_max_duration="1800" idle_mode="true" ...'
$applied = $config->set('scanner idle_mode=false');   // true = applied without a restart
$config->history();
$config->moduleSwitches();                         // notify / audit modules
```

### Backups of IAM and bucket metadata

```php
// app/Console/Commands/BackupRustFsMetadata.php
public function handle(): void
{
    $admin = RustFs::admin();
    $stamp = now()->format('Y-m-d');

    Storage::disk('backups')->put("rustfs/iam-{$stamp}.zip", $admin->iam()->export());
    Storage::disk('backups')->put("rustfs/buckets-{$stamp}.zip", $admin->buckets()->exportMetadata());
}

// Restore
$admin->iam()->import(Storage::disk('backups')->get('rustfs/iam-2026-10-23.zip'));
$admin->buckets()->importMetadata(Storage::disk('backups')->get('rustfs/buckets-2026-10-23.zip'));
```

### Live request tracing

```php
foreach (RustFs::admin()->profiling()->traceEvents(['s3' => true, 'err' => true], limit: 50) as $event) {
    logger()->info('rustfs', [$event['funcname'] ?? null, $event['path'] ?? null, $event['duration'] ?? null]);
}
```

### Anything else: raw admin requests

Every RustFS admin route is reachable, including ones without a wrapper:

```php
RustFs::admin()->request('GET', '/v4/heal/replacement-recovery');
RustFs::admin()->request('POST', '/v3/speedtest/object', ['size' => 64 * 1024 * 1024, 'duration' => '10s']);
$response = RustFs::admin()->send('GET', '/v3/profiling/download');   // raw PSR-7 response for binary data
```

The [admin route map](docs/admin-route-map.md) lists every wrapped method and its endpoint.

---

## Health checks

```php
RustFs::health()->live();          // the process is serving
RustFs::health()->ready();         // ready for traffic
RustFs::health()->cluster();       // write quorum
RustFs::health()->clusterRead();   // read quorum
```

Use them in Laravel's health route or a scheduler:

```php
// routes/console.php
Schedule::command('rustfs:health')->everyFiveMinutes()->emailOutputOnFailure('ops@example.com');
```

## Artisan commands

```bash
php artisan rustfs:health                                 # exits non-zero if a probe fails
php artisan rustfs:info [--json]                          # mode, deployment, usage, servers

php artisan rustfs:bucket list
php artisan rustfs:bucket create invoices --lock          # object lock (WORM)
php artisan rustfs:bucket create docs --versioned
php artisan rustfs:bucket info media
php artisan rustfs:bucket versioning media on|off
php artisan rustfs:bucket quota media 10737418240|clear
php artisan rustfs:bucket delete scratch --force

php artisan rustfs:user list
php artisan rustfs:user create alice --policy=readwrite   # prints a generated secret
php artisan rustfs:user disable|enable|info|delete alice
php artisan rustfs:user policy alice --policy=readonly
```

All commands accept `--connection=<name>`.

## Multiple RustFS deployments

```php
// config/rustfs.php
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
RustFs::connection('backup')->objects()->upload('db.sql.gz', $path);
RustFs::connection('backup')->admin()->system()->info();

// Disks pick a connection with "connection"
'offsite' => ['driver' => 'rustfs', 'connection' => 'backup', 'root' => 'nightly'],
```

Dependency injection works too:

```php
use Siberfx\RustFs\RustFsManager;

public function __construct(private RustFsManager $rustfs) {}

$this->rustfs->connection('backup')->buckets()->list();
```

## Error handling

```php
use Aws\S3\Exception\S3Exception;
use Siberfx\RustFs\Exceptions\AdminApiException;
use Siberfx\RustFs\Exceptions\RustFsException;

try {
    RustFs::admin()->groups()->delete('editors');
} catch (AdminApiException $e) {
    $e->status;       // 400
    $e->errorCode;    // "InvalidRequest"
    $e->getMessage(); // "RustFS admin DELETE /rustfs/admin/v3/group/editors failed with 400 [InvalidRequest]: group is not empty"
    $e->requestId;    // for the server logs
}

try {
    RustFs::objects('media')->get('missing.txt');
} catch (S3Exception $e) {
    $e->getAwsErrorCode();   // "NoSuchKey"
}
```

- `AdminApiException` extends `RustFsException`. `RustFsException` also covers configuration errors, such as an unknown connection or a missing bucket.
- Reads of settings that were never configured return `null` / `[]` instead of throwing (`policy()`, `lifecycle()`, `encryption()`, `tags()`, ...).
- `head()` returns `null` for missing objects, and `exists()` returns `false`.

## Testing your application

**Files only:** `Storage::fake('media')` works as usual.

**Admin API:** give the manager a connection with a mocked HTTP client:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Siberfx\RustFs\ConnectionConfig;
use Siberfx\RustFs\RustFsConnection;
use Siberfx\RustFs\RustFsManager;

it('provisions tenant storage', function () {
    $mock = new MockHandler([new Response(200, [], '{}'), new Response(200, [], '{}')]);

    app(RustFsManager::class)->setConnection('default', new RustFsConnection(
        ConnectionConfig::fromArray(['endpoint' => 'http://rustfs.test', 'key' => 'k', 'secret' => 's']),
        new Client(['handler' => HandlerStack::create($mock)]),
    ));

    // ... call your code and assert on the requests
});
```

**S3 API:** pass an `Aws\S3\S3Client` with an `Aws\MockHandler` as the third constructor argument. See [docs/09](docs/09-testing-and-troubleshooting.md#faking-rustfs-in-your-application-tests).

## RustFS behaviours this package handles

These were found by testing against a real server:

| RustFS behaviour | What the package does |
| --- | --- |
| `add-user` ignores its `policy` field | `users()->create(..., policy:)` attaches the policy with a second call |
| `/v3/info` and `/v3/storageinfo` wrap their data in `{"info": ...}` | `info()` / `storageInfo()` unwrap it (`rawInfo()` keeps everything) |
| Quota/usage calls return `503` until the scanner has seen a new bucket | 503 responses are retried with backoff and `Retry-After` support |
| Notification target types are sub-systems (`notify_webhook`) | Pass `'webhook'`; the `notify_`/`audit_` prefix is added |
| Module switch updates must include both flags | The flag you don't pass keeps its current value |
| Object ACLs are accepted but ignored | Use `makePublic()` / bucket policies (documented above) |
| `/minio/admin` expects encrypted bodies | The plain-JSON `/rustfs/admin` prefix is used |

## Testing

```bash
composer test       # 150+ unit and feature tests, no server needed
composer analyse    # PHPStan level 6
composer format     # Laravel Pint

# End-to-end against a real RustFS (here: Laravel Herd)
RUSTFS_LIVE_ENDPOINT=http://127.0.0.1:9000 RUSTFS_LIVE_KEY=herd RUSTFS_LIVE_SECRET=secretkey \
    vendor/bin/pest --group=live
```

CI runs the unit suite on PHP 8.4 and 8.5 and the live suite against `rustfs/rustfs:1.0.3` on every push.

## Documentation

The in-depth guides are in [`docs/`](docs/README.md):

1. [Installation & configuration](docs/01-installation-and-configuration.md)
2. [Filesystem disk](docs/02-filesystem-disk.md)
3. [Buckets](docs/03-buckets.md)
4. [Objects](docs/04-objects.md)
5. [Admin: identity & access](docs/05-admin-iam.md)
6. [Admin: cluster operations](docs/06-admin-cluster.md)
7. [Admin: security, replication & events](docs/07-admin-security-replication.md)
8. [Artisan commands](docs/08-artisan-commands.md)
9. [Errors, testing & troubleshooting](docs/09-testing-and-troubleshooting.md)
10. [Admin API route map](docs/admin-route-map.md)

## Changelog & license

See [CHANGELOG.md](CHANGELOG.md). Released under the [MIT license](LICENSE).
