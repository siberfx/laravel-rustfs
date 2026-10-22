# 9. Errors, testing & troubleshooting

## Exceptions

| Exception | Thrown by | When |
| --- | --- | --- |
| `Siberfx\RustFs\Exceptions\AdminApiException` | Admin API | The server answered `4xx`/`5xx` (after any 503 retries) |
| `Siberfx\RustFs\Exceptions\RustFsException` | Package | Configuration problems (unknown connection, missing bucket/endpoint), `Buckets::empty()` failures |
| `Aws\S3\Exception\S3Exception` | S3 API, disk | Any S3 error except "not configured" reads, which return `null` / `[]` |
| `GuzzleHttp\Exception\ConnectException` | Admin API | The server is unreachable |

`AdminApiException` extends `RustFsException`, so you can catch everything from the package with one catch block:

```php
use Siberfx\RustFs\Exceptions\AdminApiException;

try {
    RustFs::admin()->users()->create('alice', '');
} catch (AdminApiException $e) {
    $e->status;      // 400
    $e->errorCode;   // "InvalidArgument" (parsed from S3-style XML or JSON error bodies)
    $e->requestId;   // x-amz-request-id, to look up in the server logs
    $e->body;        // raw response body
    $e->getMessage();
    // "RustFS admin PUT /rustfs/admin/v3/add-user failed with 400 [InvalidArgument]: secret key is required"
}
```

For S3 errors, use the SDK's accessors: `$e->getAwsErrorCode()` (`NoSuchKey`, `AccessDenied`...) and `$e->getStatusCode()`.

## Retries

- **Admin API**: responses with status `503` are retried `admin_retries` times (default 3). The wait is `admin_retry_delay_ms × attempt`, or `Retry-After` when the server sends it, capped at 30 s per wait. Every attempt is signed again. Set `admin_retries` to `0` to turn this off.
- **S3 API**: the AWS SDK's own retry strategy applies. To change it, set `retries` on the client options, for example `'retries' => ['mode' => 'standard', 'max_attempts' => 5]` on a disk.

## Faking RustFS in your application tests

**Filesystem only:** `Storage::fake('media')` replaces the disk with a local one. Standard filesystem methods keep working. RustFS extras (`setTags()`, `objects()`...) do not exist on a fake disk.

**Admin API:** give the manager a connection that uses a mocked Guzzle client:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Siberfx\RustFs\ConnectionConfig;
use Siberfx\RustFs\RustFsConnection;
use Siberfx\RustFs\RustFsManager;

$history = [];
$stack = HandlerStack::create(new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], '{"alice":{"status":"enabled"}}'),
]));
$stack->push(Middleware::history($history));

app(RustFsManager::class)->setConnection('default', new RustFsConnection(
    ConnectionConfig::fromArray(['endpoint' => 'http://rustfs.test', 'key' => 'k', 'secret' => 's']),
    new Client(['handler' => $stack]),
));

// ... run the code under test ...

expect($history[0]['request']->getUri()->getPath())->toBe('/rustfs/admin/v3/list-users');
```

**S3 API:** pass your own `Aws\S3\S3Client` as the third constructor argument. Give it an `Aws\MockHandler`, or a `handler` callable that returns `Aws\Result` promises:

```php
$mock = new Aws\MockHandler([new Aws\Result(['Buckets' => [['Name' => 'media']]])]);
$config = ConnectionConfig::fromArray(['endpoint' => 'http://rustfs.test', 'key' => 'k', 'secret' => 's']);

app(RustFsManager::class)->setConnection('default', new RustFsConnection(
    $config,
    null,
    new Aws\S3\S3Client($config->s3ClientOptions() + ['handler' => $mock]),
));
```

The package's own test suite (`tests/Unit`) uses both techniques and is a good source of more examples.

## This package's test suite

```bash
composer install
composer test        # unit + feature tests, no server needed (Pest, Orchestra Testbench)
composer analyse     # PHPStan level 6
composer format      # Laravel Pint
```

### Live tests against a real RustFS

`tests/Live` runs end-to-end scenarios: health probes, signed admin calls, versioned round trips, presigned URLs, multipart uploads, lifecycle/CORS/policies, object lock, the IAM lifecycle, ACL behaviour, IAM export, quotas, the filesystem disk and event notifications. These tests are excluded by default and need a server:

```bash
# Laravel Herd
RUSTFS_LIVE_ENDPOINT=http://127.0.0.1:9000 RUSTFS_LIVE_KEY=herd RUSTFS_LIVE_SECRET=secretkey \
    vendor/bin/pest --group=live

# Docker
docker run -d --name rustfs-test -p 9000:9000 \
    -e RUSTFS_ACCESS_KEY=rustfsadmin -e RUSTFS_SECRET_KEY=rustfsadmin rustfs/rustfs:1.0.3
RUSTFS_LIVE_ENDPOINT=http://127.0.0.1:9000 vendor/bin/pest --group=live
```

The tests create buckets, users, groups and policies with random `pest-` names and delete them afterwards.

The notification test changes server-wide settings (it temporarily enables the notify module and creates a webhook target), so it is opt-in. Set `RUSTFS_LIVE_NOTIFY_QUEUE_DIR` to an absolute directory that exists **on the RustFS host**, for example `/tmp` in Docker. The test restores the original module switches when it finishes.

GitHub Actions runs the live suite against `rustfs/rustfs` on every push (see `.github/workflows/tests.yml`).

## Troubleshooting

### `SignatureDoesNotMatch` / `403` on every call
- The key/secret are wrong, or the **region** does not match the server's configured region (`RUSTFS_REGION`).
- `RUSTFS_ENDPOINT` points at the **console** port (`9001`) instead of the API port (`9000`).
- A proxy in front of RustFS rewrites the `Host` header or the path. SigV4 signs both.

### Admin calls fail but S3 calls work
- The credentials are not admin-level. Check with `RustFs::admin()->account()->isAdmin()`.
- `admin_prefix` was changed to `/minio/admin`, which expects madmin-encrypted bodies. Use `/rustfs/admin`.

### Public URLs return `403`
RustFS ignores object ACLs (`'visibility' => 'public'`). Add a read policy with `RustFs::buckets()->makePublic($bucket, $prefix)`.

### `503 authoritative bucket usage is not available yet`
This happens on quota and usage calls right after a bucket is created. It is retried automatically. If it persists, the scanner is disabled or busy: check `diagnostics()->scannerStatus()`.

### Notification target errors
| Message | Fix |
| --- | --- |
| `notify module is disabled` | `config()->updateModuleSwitches(notify: true)` or start RustFS with `RUSTFS_NOTIFY_ENABLE=true` |
| `unsupported notification target type` | Use a known type (`webhook`, `kafka`, ...). The package adds the `notify_`/`audit_` prefix |
| `outbound URL host ... is not allowed: loopback address` | Add the origin to `RUSTFS_OUTBOUND_ALLOW_ORIGINS` on the server and restart it |
| `queue_dir does not exist` / `must be an absolute path` | Pass an absolute `queue_dir` that exists on the RustFS host |

### A broken notification target blocks the notify module
If a target was saved but cannot start (for example because of an invalid `queue_dir`), RustFS can get stuck. Enabling notify fails with `runtime convergence failed`, and removing the target fails with `notify module is disabled`. Delete the target's config entry directly, then enable the module again:

```php
$admin = RustFs::admin();
$admin->config()->delete('notify_webhook:primary');   // "<subsystem>:<target name>"
$admin->config()->updateModuleSwitches(notify: true);
```

### Requests time out on large transfers
Raise `RUSTFS_TIMEOUT`. For uploads, prefer `objects()->upload()` (multipart) or presigned URLs, so the transfer doesn't run through your PHP process in one request.

### Windows / Laravel Herd notes
- Herd's RustFS listens on `localhost:9000` (credentials `herd` / `secretkey`). The console is on `9001`.
- Directory settings such as `queue_dir` must be Windows absolute paths (`C:/...`).
