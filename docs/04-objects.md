# 4. Objects

`RustFs::objects($bucket)` returns a `Siberfx\RustFs\S3\Objects` instance bound to one bucket. If you leave out `$bucket`, the connection's default bucket (`RUSTFS_BUCKET`) is used. If there is none, a `RustFsException` is thrown.

```php
use Siberfx\RustFs\Facades\RustFs;

$objects = RustFs::objects('media');
$objects = RustFs::objects();                      // default bucket
$logs    = $objects->bucket('logs');               // same client, another bucket
$objects = Storage::disk('media')->objects();      // from a disk
```

Write and metadata methods return the S3 result as a plain array without the SDK's `@metadata`, for example `['ETag' => '"..."', 'VersionId' => '...', 'ObjectURL' => '...']`.

## Uploading

### Single request: `put()`

```php
$objects->put('docs/readme.txt', 'Hello');                     // string
$objects->put('docs/report.pdf', fopen($path, 'rb'));          // resource
$objects->put('docs/data.json', $psr7Stream);                  // PSR-7 stream

$result = $objects->put('avatars/42.png', $png, [
    'ContentType' => 'image/png',
    'CacheControl' => 'public, max-age=31536000',
    'Metadata' => ['owner' => 'alice'],                         // sent as x-amz-meta-owner
    'Tagging' => 'team=web&env=prod',
    'ServerSideEncryption' => 'aws:kms',
    'SSEKMSKeyId' => 'app-key',
]);
$result['VersionId'];   // set when the bucket is versioned
```

`$options` accepts any `PutObject` parameter, including `ObjectLockMode`, `ObjectLockRetainUntilDate`, `ObjectLockLegalHoldStatus`, `ContentDisposition`, `ContentEncoding`, `StorageClass` and the `SSECustomer*` keys.

### Large files: `upload()`

`upload()` uploads small payloads in one request. Above a threshold (16 MiB by default) it switches to a parallel multipart upload. `$source` can be a **file path**, a resource, a stream or a string.

```php
$objects->upload('videos/intro.mp4', '/var/media/intro.mp4');

$objects->upload(
    'backups/db.sql.gz',
    '/tmp/db.sql.gz',
    params: ['ContentType' => 'application/gzip'],
    multipartThreshold: 64 * 1024 * 1024,
    partSize: 32 * 1024 * 1024,     // S3 minimum is 5 MiB
    concurrency: 8,
);
```

### Client-driven multipart (resumable, browser chunked uploads)

```php
$uploadId = $objects->createMultipartUpload('big/archive.zip', ['ContentType' => 'application/zip']);

// Option A: the server uploads each part
$parts[] = $objects->uploadPart('big/archive.zip', $uploadId, 1, $chunk1);   // ['PartNumber' => 1, 'ETag' => '"..."']

// Option B: hand the client a presigned URL for each part
$url = $objects->temporaryPartUploadUrl('big/archive.zip', $uploadId, partNumber: 2, expires: '+1 hour');
// The client PUTs the bytes to $url and sends back the ETag response header.

$objects->completeMultipartUpload('big/archive.zip', $uploadId, $parts);    // parts are sorted for you

$objects->abortMultipartUpload('big/archive.zip', $uploadId);
$objects->multipartUploads('big/');                         // uploads still in progress
$objects->parts('big/archive.zip', $uploadId);              // parts uploaded so far
```

## Copying

```php
$objects->copy('a.jpg', 'b.jpg');                                   // same bucket
$objects->copy('photos/a b.jpg', 'backup/a.jpg', sourceBucket: 'inbox');
$objects->copy('a.jpg', 'a-v1.jpg', sourceVersionId: $versionId);   // a specific version
$objects->copy('a.jpg', 'a.jpg', options: [                         // rewrite metadata in place
    'MetadataDirective' => 'REPLACE',
    'ContentType' => 'image/jpeg',
]);
```

The copy source is URL-encoded for you.

## Downloading

```php
$objects->get('docs/readme.txt');                        // string
$objects->get('docs/readme.txt', versionId: $v1);        // an older version
$stream = $objects->stream('videos/intro.mp4');           // PSR-7 stream, not buffered in memory
$stream = $objects->stream('videos/intro.mp4', options: ['Range' => 'bytes=0-1048575']);
$objects->download('backups/db.sql.gz', '/tmp/db.sql.gz');   // saved directly to disk
```

## Metadata and existence

```php
$meta = $objects->head('avatars/42.png');   // null if the object does not exist
// ['ContentLength' => 1234, 'ContentType' => 'image/png', 'ETag' => '"..."',
//  'LastModified' => DateTimeResult, 'Metadata' => ['owner' => 'alice'], 'VersionId' => '...']

$objects->exists('avatars/42.png');         // bool
```

## Listing

`list()` and `versions()` are **generators**. They page through results lazily, so a bucket with millions of keys never sits in memory at once.

```php
foreach ($objects->list('photos/2026/') as $object) {
    // ['Key' => 'photos/2026/a.jpg', 'Size' => 123, 'LastModified' => ..., 'ETag' => ..., 'StorageClass' => ...]
}

// Only one level. Sub-"folders" come back as ['Prefix' => 'photos/2026/', 'IsDirectory' => true]
foreach ($objects->list('photos/', recursive: false) as $entry) { /* ... */ }

// Every version and delete marker
foreach ($objects->versions('docs/') as $version) {
    // [..., 'VersionId' => '...', 'IsLatest' => true, 'IsDeleteMarker' => false]
}
```

## Deleting

```php
$objects->delete('tmp/a.txt');                          // in a versioned bucket this adds a delete marker
$objects->delete('tmp/a.txt', versionId: $v1);          // removes that version for good
$objects->delete('locked.pdf', $v1, bypassGovernanceRetention: true);

// Batches of 1000, from any iterable (even a generator)
$errors = $objects->deleteMany(['a', 'b', ['Key' => 'c', 'VersionId' => 'v3']]);
// $errors lists the entries the server refused, e.g. [['Key' => 'c', 'Code' => 'AccessDenied', 'Message' => '...']]

$objects->deletePrefix('tmp/');                         // current versions below a prefix; returns the count
```

To delete everything in a bucket, including every version, use `RustFs::buckets()->empty($bucket)`.

## Object tags

```php
$objects->putTags('docs/a.pdf', ['classification' => 'internal']);
$objects->tags('docs/a.pdf');                    // ['classification' => 'internal']
$objects->tags('docs/a.pdf', versionId: $v1);
$objects->deleteTags('docs/a.pdf');
```

Tags can drive lifecycle rules (`'Filter' => ['Tag' => ['Key' => ..., 'Value' => ...]]`) and bucket policies.

## Object lock: retention & legal hold

These work only in buckets created with `objectLock: true`.

```php
// Retention: no deletes or overwrites of this version until the date
$objects->putRetention('contracts/2026.pdf', 'COMPLIANCE', new DateTimeImmutable('+7 years'), versionId: $v);
$objects->retention('contracts/2026.pdf', $v);   // ['Mode' => 'COMPLIANCE', 'RetainUntilDate' => DateTime] or null

// Shorten a GOVERNANCE retention (needs s3:BypassGovernanceRetention)
$objects->putRetention('drafts/a.pdf', 'GOVERNANCE', new DateTimeImmutable('+1 day'), $v, bypassGovernanceRetention: true);

// Legal hold: no expiry, stays until it is removed
$objects->putLegalHold('evidence/1.zip', true, $v);
$objects->legalHold('evidence/1.zip', $v);      // bool
$objects->putLegalHold('evidence/1.zip', false, $v);
```

You can also set these on upload with `put()` options: `ObjectLockMode`, `ObjectLockRetainUntilDate` and `ObjectLockLegalHoldStatus`.

## Presigned URLs

Presigned URLs are signed locally and never contact the server. `$expires` can be a `DateTimeInterface` or a relative string such as `'+15 minutes'` (the default).

```php
// Download
$url = $objects->temporaryUrl('invoices/42.pdf', '+10 minutes', [
    'ResponseContentDisposition' => 'attachment; filename="invoice-42.pdf"',
    'ResponseContentType' => 'application/pdf',
    'VersionId' => $v1,
]);

// Upload with PUT. Send Content-Type: RustFS stores it, but does not enforce the signed value (use presigned POST for limits)
$url = $objects->temporaryUploadUrl('incoming/photo.png', now()->addMinutes(5), ['ContentType' => 'image/png']);
```

### Presigned POST (HTML form uploads with server-enforced rules)

```php
$form = $objects->presignedPost(
    formInputs: ['key' => 'uploads/${filename}', 'Content-Type' => 'image/jpeg'],
    conditions: [
        ['starts-with', '$key', 'uploads/'],
        ['content-length-range', 1, 10 * 1024 * 1024],
        ['eq', '$Content-Type', 'image/jpeg'],
    ],
    expires: '+30 minutes',
);
// $form['url']    -> http://127.0.0.1:9000/media
// $form['fields'] -> hidden inputs: key, Policy, X-Amz-Algorithm, X-Amz-Credential, X-Amz-Date, X-Amz-Signature, ...
```

```blade
<form action="{{ $form['url'] }}" method="POST" enctype="multipart/form-data">
    @foreach ($form['fields'] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <input type="file" name="file">  {{-- the file input must be the last field --}}
    <button>Upload</button>
</form>
```

## S3 Select

S3 Select runs SQL on the server against a CSV, JSON or Parquet object, so only the matching rows travel over the network.

```php
// Raw records (JSON lines by default)
$jsonLines = $objects->select('reports/sales.csv', "SELECT s.country, s.total FROM S3Object s WHERE s.total > '1000'");

// Decoded rows
$rows = $objects->selectRows('reports/sales.csv', "SELECT * FROM S3Object s WHERE s.country = 'TR'");

// JSON input, gzip compressed
$rows = $objects->selectRows(
    'events/2026-10-23.json.gz',
    "SELECT e.type FROM S3Object[*] e WHERE e.level = 'error'",
    input: ['JSON' => ['Type' => 'LINES'], 'CompressionType' => 'GZIP'],
);

// CSV output
$csv = $objects->select('data.csv', 'SELECT * FROM S3Object', output: ['CSV' => []]);
```

## Restoring tiered objects

Objects that a lifecycle rule moved to a remote tier can be restored for a while:

```php
$objects->restore('archive/2020.tar', days: 3);
```

## Anything else

`RustFs::s3()` (or `$disk->getClient()`) returns the configured `Aws\S3\S3Client`, so you can call any S3 operation the SDK supports:

```php
RustFs::s3()->getObjectAttributes(['Bucket' => 'media', 'Key' => 'a.jpg', 'ObjectAttributes' => ['ETag', 'ObjectSize']]);
```

Next: [Admin: identity & access](05-admin-iam.md).
