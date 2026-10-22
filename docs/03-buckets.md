# 3. Buckets

`RustFs::buckets()` returns a `Siberfx\RustFs\S3\Buckets` instance. It covers the bucket lifecycle and every bucket setting RustFS exposes through the S3 API.

```php
use Siberfx\RustFs\Facades\RustFs;

$buckets = RustFs::buckets();                       // default connection
$buckets = RustFs::connection('backup')->buckets();
```

Read methods return `null` or `[]` when a setting has never been configured, so you do not have to catch `NoSuch...` errors. Any other S3 error (access denied, missing bucket...) is thrown as `Aws\S3\Exception\S3Exception`.

## Creating, inspecting and deleting

```php
$buckets->list();                       // [['name' => 'media', 'created' => DateTimeInterface|null], ...]
$buckets->exists('media');              // bool
$buckets->location('media');            // region string ('' = default)

$buckets->create('media');
$buckets->create('documents', versioning: true);
$buckets->create('invoices', objectLock: true);   // WORM. Turns on versioning and cannot be undone

$buckets->delete('scratch');                      // the bucket must be empty
$buckets->delete('scratch', force: true);         // removes every version and delete marker first
$buckets->empty('scratch');                       // returns the number of entries deleted
$buckets->empty('locked', bypassGovernanceRetention: true);
```

`create()` waits until the bucket exists before returning, so you can use it straight away.

`empty()` throws `RustFsException` if any entry could not be deleted, for example an object under COMPLIANCE retention.

## Versioning

```php
$buckets->enableVersioning('documents');
$buckets->suspendVersioning('documents');
$buckets->versioning('documents');   // "Enabled", "Suspended" or "" (never enabled)
```

## Object lock (WORM)

Object lock can only be turned on when the bucket is created: `create($name, objectLock: true)`. After that you can set a **default retention** that applies to every new object:

```php
$buckets->putObjectLock('invoices', 'COMPLIANCE', years: 7);
$buckets->putObjectLock('drafts', 'GOVERNANCE', days: 30);
$buckets->putObjectLock('invoices', 'COMPLIANCE');        // keep lock enabled, remove the default rule

$buckets->objectLock('invoices');
// ['ObjectLockEnabled' => 'Enabled', 'Rule' => ['DefaultRetention' => ['Mode' => 'COMPLIANCE', 'Years' => 7]]]
```

- **GOVERNANCE**: users with the `s3:BypassGovernanceRetention` permission can delete or shorten retention by passing `bypassGovernanceRetention: true`.
- **COMPLIANCE**: nobody can delete the version before the retain-until date, not even root.

To set retention or a legal hold on a single object, see [Objects → Object lock](04-objects.md#object-lock-retention--legal-hold).

## Lifecycle (ILM)

```php
$buckets->putLifecycle('logs', [
    [
        'ID' => 'expire-debug-logs',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => 'debug/'],
        'Expiration' => ['Days' => 7],
    ],
    [
        'ID' => 'cleanup-old-versions',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => ''],
        'NoncurrentVersionExpiration' => ['NoncurrentDays' => 30],
    ],
    [
        'ID' => 'abort-stale-multipart',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => ''],
        'AbortIncompleteMultipartUpload' => ['DaysAfterInitiation' => 2],
    ],
    [
        // Move objects to a remote tier (create it first with Admin::tiers()->add())
        'ID' => 'archive-to-cold',
        'Status' => 'Enabled',
        'Filter' => ['Prefix' => 'archive/'],
        'Transitions' => [['Days' => 90, 'StorageClass' => 'COLD']],
    ],
]);

$buckets->lifecycle('logs');        // list of rules ([] when none)
$buckets->deleteLifecycle('logs');
```

The rules use the standard S3 `LifecycleConfiguration` shape. `putLifecycle()` replaces the whole configuration, so include every rule you want to keep.

## Replication rules

Bucket replication takes two steps:

1. Register the destination as a **remote target** with the Admin API. This returns an ARN.
2. Put a replication configuration on the source bucket that references that ARN.

Both buckets need versioning enabled.

```php
$arn = RustFs::admin()->replication()->setRemoteTarget('photos', [
    'endpoint' => 'dr-site.example.com:9000',
    'secure' => true,
    'targetbucket' => 'photos-replica',
    'credentials' => ['accessKey' => 'replicator', 'secretKey' => '...'],
    'type' => 'replication',
]);

$buckets->putReplication('photos', [
    'Rules' => [[
        'ID' => 'to-dr',
        'Status' => 'Enabled',
        'Priority' => 1,
        'Filter' => ['Prefix' => ''],
        'DeleteMarkerReplication' => ['Status' => 'Enabled'],
        'Destination' => ['Bucket' => $arn],
    ]],
]);

$buckets->replication('photos');        // configuration array or null
$buckets->deleteReplication('photos');
```

For replication metrics, diffs and the retry queue, see [Admin → Replication](07-admin-security-replication.md#bucket-replication-targets).

## Default encryption

```php
$buckets->putEncryption('secure', 'AES256');                // SSE-S3
$buckets->putEncryption('secure', 'aws:kms', 'my-key-id');  // SSE-KMS with a RustFS KMS key
$buckets->encryption('secure');                             // configuration or null
$buckets->deleteEncryption('secure');
```

SSE-KMS needs RustFS KMS to be configured and running (see [KMS](07-admin-security-replication.md#kms)).

## Bucket policy

```php
$buckets->putPolicy('media', [
    'Version' => '2012-10-17',
    'Statement' => [[
        'Effect' => 'Allow',
        'Principal' => ['AWS' => ['*']],
        'Action' => ['s3:GetObject'],
        'Resource' => ['arn:aws:s3:::media/public/*'],
    ]],
]);

// The same thing in one line: anonymous read on a prefix (or the whole bucket with '')
$buckets->makePublic('media', 'public/');

$buckets->policy('media');         // decoded array, or null
$buckets->deletePolicy('media');
```

`putPolicy()` also accepts a JSON string.

## Tags

```php
$buckets->putTags('media', ['team' => 'web', 'cost-center' => '42']);
$buckets->tags('media');           // ['team' => 'web', 'cost-center' => '42']
$buckets->deleteTags('media');
```

## CORS

```php
$buckets->putCors('media', [[
    'AllowedOrigins' => ['https://app.example.com'],
    'AllowedMethods' => ['GET', 'PUT'],
    'AllowedHeaders' => ['*'],
    'ExposeHeaders' => ['ETag'],
    'MaxAgeSeconds' => 3600,
]]);

$buckets->cors('media');
$buckets->deleteCors('media');
```

Browser uploads to presigned URLs need a CORS rule that allows `PUT` from your app's origin.

## Event notifications

Notifications are delivered to **targets** (webhook, Kafka, AMQP, MQTT, NATS, NSQ, Redis, MySQL, PostgreSQL, Pulsar, Elasticsearch) that you configure with the Admin API. Each target has an ARN of the form `arn:rustfs:sqs:{region}:{target name}:{type}`, and buckets subscribe to it.

Things to know before you start (all observed on RustFS 1.0.x):

- The **notify module must be enabled** first. Until it is, target calls fail with `notify module is disabled`.
- `queue_dir` must be an **absolute path that already exists on the RustFS host**. The server's default (`/opt/rustfs/events`) is not valid on Windows hosts such as Laravel Herd for Windows.
- RustFS refuses **loopback/private endpoints** (SSRF protection) unless their origin is listed in `RUSTFS_OUTBOUND_ALLOW_ORIGINS` on the server.
- A target that cannot start can block the notify module. See [Troubleshooting](09-testing-and-troubleshooting.md#a-broken-notification-target-blocks-the-notify-module) for how to recover.

```php
$admin = RustFs::admin();

// 0. The notify module is off by default. Turn it on once (or start RustFS with RUSTFS_NOTIFY_ENABLE=true)
$admin->config()->updateModuleSwitches(notify: true);

// 1. Create the target (once per deployment)
$admin->notifications()->set('webhook', 'primary', [
    'endpoint' => 'https://app.example.com/rustfs/events',
    'auth_token' => 'Bearer s3cr3t',
    'queue_dir' => '/var/lib/rustfs/events',   // must exist on the RustFS host and be an absolute path
    'enable' => true,
]);
$arn = $admin->notifications()->arns()[0];   // "arn:rustfs:sqs:us-east-1:primary:webhook"

// 2. Subscribe the bucket. addNotification() keeps the existing subscriptions
$buckets->addNotification('media', $arn, ['s3:ObjectCreated:*'], prefix: 'uploads/', suffix: '.jpg');

// Full control over the whole configuration
$buckets->putNotifications('media', [
    'QueueConfigurations' => [[
        'QueueArn' => $arn,
        'Events' => ['s3:ObjectCreated:*', 's3:ObjectRemoved:*'],
        'Filter' => ['Key' => ['FilterRules' => [['Name' => 'prefix', 'Value' => 'uploads/']]]],
    ]],
]);

$buckets->notifications('media');
$buckets->clearNotifications('media');
```

## Method reference

| Method | Returns |
| --- | --- |
| `list()` | `list<array{name, created}>` |
| `exists($bucket)` | `bool` |
| `create($bucket, $objectLock = false, $versioning = false)` | `void` |
| `delete($bucket, $force = false)` | `void` |
| `empty($bucket, $bypassGovernanceRetention = false)` | `int` deleted entries |
| `location($bucket)` | `string` |
| `versioning($bucket)` / `enableVersioning()` / `suspendVersioning()` | `string` / `void` |
| `objectLock($bucket)` / `putObjectLock($bucket, $mode, $days, $years)` | `?array` / `void` |
| `lifecycle()` / `putLifecycle($bucket, $rules)` / `deleteLifecycle()` | `array` / `void` |
| `replication()` / `putReplication($bucket, $config)` / `deleteReplication()` | `?array` / `void` |
| `encryption()` / `putEncryption($bucket, $algorithm, $kmsKeyId)` / `deleteEncryption()` | `?array` / `void` |
| `policy()` / `putPolicy($bucket, $policy)` / `makePublic($bucket, $prefix)` / `deletePolicy()` | `?array` / `void` |
| `tags()` / `putTags($bucket, $tags)` / `deleteTags()` | `array<string,string>` / `void` |
| `cors()` / `putCors($bucket, $rules)` / `deleteCors()` | `array` / `void` |
| `notifications()` / `putNotifications()` / `addNotification()` / `clearNotifications()` | `array` / `void` |

Next: [objects](04-objects.md).
