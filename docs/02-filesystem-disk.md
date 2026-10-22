# 2. Filesystem disk

The package registers a `rustfs` driver for Laravel's `Storage` facade. It is built on Laravel's own `s3` driver, so everything that works on an S3 disk works here too. Configuration comes from your RustFS connection, so a disk definition can be one line.

## Defining disks

```php
// config/filesystems.php
'disks' => [

    // Everything comes from the default RustFS connection (including RUSTFS_BUCKET)
    'rustfs' => [
        'driver' => 'rustfs',
    ],

    // A different bucket
    'media' => [
        'driver' => 'rustfs',
        'bucket' => 'media',
    ],

    // Another RustFS deployment, with every key prefixed by "nightly/"
    'backups' => [
        'driver' => 'rustfs',
        'connection' => 'backup',
        'root' => 'nightly',
        'throw' => true,
    ],

    // Public URLs served by a CDN in front of RustFS
    'assets' => [
        'driver' => 'rustfs',
        'bucket' => 'assets',
        'url' => 'https://cdn.example.com',
    ],
],
```

### Disk options

| Option | Default | Description |
| --- | --- | --- |
| `connection` | `rustfs.default` | Which RustFS connection supplies the defaults |
| `bucket` | connection `bucket` | **Required**, either here or on the connection |
| `endpoint`, `key`, `secret`, `token`, `region`, `use_path_style_endpoint`, `http` | from the connection | Set any of these to override the connection |
| `url` | `{endpoint}/{bucket}` | Base used by `Storage::url()` |
| `root` | – | Prefix added to every path on this disk |
| `visibility` | `private` | Laravel sends a `public-read` ACL for `public`. **RustFS accepts but ignores object ACLs**, so this does not make files anonymously readable. See [Public files](#public-files) |
| `throw` | `false` | Throw exceptions instead of returning `false` |
| `options`, `stream_reads`, `report`, ... | – | Passed through to Laravel's S3 driver unchanged |

Without a bucket the disk throws `Siberfx\RustFs\Exceptions\RustFsException: The rustfs disk needs a "bucket"...`.

You can also build a disk at runtime:

```php
$disk = Storage::build(['driver' => 'rustfs', 'bucket' => 'tenant-' . $tenant->id]);
```

## Everyday use

```php
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('media');

$disk->put('avatars/42.png', $png);
$disk->putFile('uploads', $request->file('photo'));          // generated name
$disk->writeStream('videos/intro.mp4', fopen($path, 'rb'));   // streams, no full read into memory

$disk->exists('avatars/42.png');
$disk->get('avatars/42.png');
$disk->readStream('videos/intro.mp4');
$disk->size('avatars/42.png');
$disk->lastModified('avatars/42.png');
$disk->mimeType('avatars/42.png');

$disk->files('avatars');            // one level
$disk->allFiles('avatars');         // recursive
$disk->directories('/');

$disk->copy('avatars/42.png', 'avatars/42-old.png');
$disk->move('tmp/a.txt', 'docs/a.txt');
$disk->delete(['tmp/1', 'tmp/2']);
$disk->deleteDirectory('tmp');
```

## Public files

RustFS decides anonymous access with **bucket policies**, not object ACLs. We tested this against RustFS 1.0.1: an object uploaded with `ACL: public-read` still returns `403` to anonymous requests, and the same object returns `200` once a read policy covers it. To serve files publicly, give the bucket (or a prefix of it) a read policy once:

```php
Storage::disk('media')->buckets()->makePublic('media', 'public/');   // or RustFs::buckets()->makePublic(...)
```

After that, every object under `public/` is reachable at its `url()`.

## URLs

```php
// Public (anonymous) URL. The object must be covered by a read policy (see "Public files")
Storage::disk('media')->url('avatars/42.png');
// http://127.0.0.1:9000/media/avatars/42.png

// Signed, expiring download URL. Works for private objects
Storage::disk('media')->temporaryUrl('invoices/2026-10.pdf', now()->addMinutes(10), [
    'ResponseContentDisposition' => 'attachment; filename="invoice.pdf"',
]);

// Signed, expiring upload URL for direct browser uploads
['url' => $url, 'headers' => $headers] = Storage::disk('media')
    ->temporaryUploadUrl('incoming/' . Str::uuid(), now()->addMinutes(5), ['ContentType' => 'image/png']);
```

The browser then sends `PUT $url` with the file as the body and its `Content-Type` header. On RustFS 1.0.x a presigned PUT does not enforce the signed type or any size limit. Use a presigned POST (see [Objects](04-objects.md#presigned-post-html-form-uploads-with-server-enforced-rules)) when the server must enforce them.

## RustFS extras on the disk

`Storage::disk(...)` returns a `Siberfx\RustFs\Filesystem\RustFsFilesystemAdapter`. It extends Laravel's `AwsS3V3Adapter` and adds these methods:

| Method | Description |
| --- | --- |
| `bucketName(): string` | The disk's bucket |
| `key(string $path): string` | `$path` with the disk `root` applied, which is the actual object key in the bucket |
| `objects(): Objects` | The full [object API](04-objects.md) bound to the disk's bucket |
| `buckets(): Buckets` | The [bucket API](03-buckets.md), using the disk's client |
| `setTags(string $path, array $tags)` | Replace an object's tags |
| `getTags(string $path): array` | Read an object's tags as `key => value` |
| `versions(string $path = '')` | Generator over object versions and delete markers below `$path` |
| `getClient(): S3Client` | Inherited: the underlying AWS client |

```php
$disk = Storage::disk('backups');          // root = "nightly"

$disk->put('db.sql.gz', $dump);
$disk->setTags('db.sql.gz', ['retention' => '30d']);

// The object API takes raw bucket keys, so apply the root with key()
$disk->objects()->putLegalHold($disk->key('db.sql.gz'), true);

foreach ($disk->versions('db.sql.gz') as $version) {
    echo $version['VersionId'], ' ', $version['LastModified']->format('c'), PHP_EOL;
}
```

> Methods on `objects()` take **bucket keys**, not disk paths. Use `$disk->key($path)` whenever the disk has a `root`.

## Testing code that uses the disk

`Storage::fake('media')` works as usual. It swaps the disk for a local one, so your code must only use standard filesystem methods in those tests. If code under test calls RustFS extras such as `setTags()` or `objects()`, see [Errors, testing & troubleshooting](09-testing-and-troubleshooting.md#faking-rustfs-in-your-application-tests).

Next: [buckets](03-buckets.md).
