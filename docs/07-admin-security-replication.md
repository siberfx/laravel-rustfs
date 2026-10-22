# 7. Admin: security, replication & events

```php
$admin = RustFs::admin();
```

## KMS

RustFS has a built-in KMS that backs SSE-KMS and SSE-S3 encryption. Supported backends are Vault KV2, Vault Transit and AWS KMS for production, and Local or Static for development.

### Service lifecycle

```php
$kms = $admin->kms();

$kms->serviceStatus();     // ['status' => ..., 'backend_type' => ..., 'healthy' => bool, 'config_summary' => ...]
$kms->status();            // fails with 500 "KMS service not initialized" until KMS is configured
$kms->config();

$kms->configure([...]);    // backend settings (fields depend on the backend)
$kms->start();
$kms->stop();
$kms->reconfigure([...]);
$kms->reload();
$kms->clearCache();
```

You can also configure KMS when starting the server, for example `rustfs server --kms-enable --kms-backend local --kms-key-dir /keys ...`.

### Keys

```php
$key = $kms->createKey('Application data key', tags: ['app' => 'billing']);   // includes key_id
$keyId = $key['key_id'];

$kms->listKeys(['limit' => 50]);
$kms->describeKey($keyId);
$kms->updateKeyDescription($keyId, 'Billing data key');
$kms->tagKey($keyId, ['env' => 'prod']);
$kms->untagKey($keyId, ['env']);

$kms->disableKey($keyId);
$kms->enableKey($keyId);
$kms->rotateKey($keyId);

$kms->deleteKey($keyId, pendingWindowInDays: 7);   // scheduled deletion
$kms->cancelKeyDeletion($keyId);
$kms->deleteKey($keyId, forceImmediate: true);     // irreversible: data encrypted with it is lost

$kms->generateDataKey($keyId, 'Aes256', ['bucket' => 'billing']);   // key spec: Aes256, Aes128 or ChaCha20
```

### Re-keying and backups

```php
$kms->startRekey(['key_id' => $keyId]);   // re-encrypt object keys sealed with older key versions
$kms->rekeyStatus();
$kms->cancelRekey();

$kms->backupStatus();
$kms->backup([...]);
$kms->restore($payload, dryRun: true);    // validate first
$kms->restore($payload);
$kms->abortRestore();
```

Use the key in buckets and uploads:

```php
RustFs::buckets()->putEncryption('billing', 'aws:kms', $keyId);
RustFs::objects('billing')->put('inv.pdf', $pdf, ['ServerSideEncryption' => 'aws:kms', 'SSEKMSKeyId' => $keyId]);
```

## Bucket replication targets

Bucket replication copies objects asynchronously to a bucket on another deployment. The rule itself is bucket configuration ([Buckets → Replication](03-buckets.md#replication-rules)). The *destination* is registered here:

```php
$replication = $admin->replication();

$arn = $replication->setRemoteTarget('photos', [
    'endpoint' => 'dr.example.com:9000',
    'secure' => true,
    'targetbucket' => 'photos-replica',
    'credentials' => ['accessKey' => 'replicator', 'secretKey' => '...'],
    'type' => 'replication',
]);
$replication->setRemoteTarget('photos', $updatedTarget, update: true);

$replication->remoteTargets('photos');
$replication->remoteTargets();                  // every bucket
$replication->metrics('photos');                // replicated / pending / failed counts and bytes
$replication->diff('photos', ['arn' => $arn]);  // objects that are not in sync
$replication->mrf('photos');                    // objects waiting for a retry
$replication->removeRemoteTarget('photos', $arn);
```

Both buckets must have versioning enabled.

## Site replication

Site replication keeps **every bucket, object, IAM entity and policy** in sync across independent deployments.

```php
$sites = $admin->siteReplication();

$sites->add([
    ['name' => 'eu', 'endpoints' => 'https://eu.example.com:9000', 'accessKey' => '...', 'secretKey' => '...'],
    ['name' => 'us', 'endpoints' => 'https://us.example.com:9000', 'accessKey' => '...', 'secretKey' => '...'],
]);

$sites->info();                          // ['enabled' => bool, 'name' => ..., 'apiVersion' => ..., 'sites' => [...]]
$sites->status(['buckets' => true, 'users' => true, 'policies' => true]);
$sites->metaInfo();
$sites->edit(['name' => 'us', 'endpoints' => 'https://us-new.example.com:9000']);
$sites->resync('start', ['name' => 'us']);       // or 'cancel'
$sites->repair();
$sites->repairStatus();
$sites->netperf(['duration' => '10s']);
$sites->remove(['sites' => ['us']]);
$sites->remove(['all' => true]);
```

## Event notification & audit targets

Both use `Siberfx\RustFs\Admin\Resources\Targets`:

| | Accessor | RustFS sub-systems |
| --- | --- | --- |
| Bucket event notifications | `$admin->notifications()` | `notify_webhook`, `notify_kafka`, `notify_amqp`, `notify_mqtt`, `notify_nats`, `notify_nsq`, `notify_redis`, `notify_mysql`, `notify_postgres`, `notify_pulsar`, `notify_elasticsearch` |
| Audit log | `$admin->audit()` | `audit_webhook`, `audit_kafka`, `audit_amqp`, `audit_mqtt`, `audit_nats`, `audit_redis`, `audit_mysql`, `audit_postgres`, `audit_pulsar` |

Pass the short type (`'webhook'`). The right prefix is added for you, and full names (`'notify_webhook'`) work too. Settings are the sub-system keys. Booleans are sent as `on`/`off`.

```php
// Both modules are off by default
$admin->config()->updateModuleSwitches(notify: true, audit: true);

$admin->notifications()->set('webhook', 'primary', [
    'endpoint' => 'https://app.example.com/rustfs/events',
    'auth_token' => 'Bearer s3cr3t',
    'queue_dir' => '/var/lib/rustfs/events',     // absolute, must exist on the RustFS host
    'queue_limit' => 100000,
    'enable' => true,
]);
$admin->notifications()->set('kafka', 'stream', [
    'brokers' => 'kafka-1:9092,kafka-2:9092',
    'topic' => 'rustfs-events',
    'queue_dir' => '/var/lib/rustfs/events',
    'enable' => true,
]);

$admin->notifications()->list();        // ['notify_enabled' => true, 'notification_endpoints' => [['account_id' => 'primary', 'service' => 'webhook', 'status' => 'online', ...]]]
$admin->notifications()->arns();        // ['arn:rustfs:sqs:us-east-1:primary:webhook', ...]
$admin->notifications()->subscriptions('webhook', 'primary');
$admin->notifications()->remove('webhook', 'primary');

$admin->audit()->set('webhook', 'siem', ['endpoint' => 'https://siem.example.com/ingest', 'enable' => true]);
$admin->audit()->list();
$admin->audit()->remove('webhook', 'siem');
```

To see which keys a sub-system accepts, run `$admin->config()->help('notify_webhook')`. Subscribing buckets to a target is covered in [Buckets → Event notifications](03-buckets.md#event-notifications).

## Bucket admin tools — `buckets()`

These bucket operations exist only in the Admin API, not in S3:

```php
$tools = $admin->buckets();

// Metadata backup: policies, lifecycle, versioning, replication, quota, tags, notification config...
$zip = $tools->exportMetadata('media');      // or exportMetadata() for every bucket
$tools->importMetadata($zip, 'media');

// Per-bucket durability override
$tools->durability('media');
$tools->setDurability('media', 'strict');
$tools->resetDurability('media');            // back to the server-wide mode

// On-demand migration: lazily pull objects from another S3 service the first time they are read
$tools->setMigration('media', [/* source endpoint, credentials, bucket */], dryRun: true);
$tools->setMigration('media', [/* ... */]);
$tools->migration('media');
$tools->migrationStatus('media');
$tools->migrationBackfill('media');
$tools->controlMigrationBackfill('media', ['action' => 'start']);
$tools->deleteMigration('media');

// Recreate the metadata of a bucket whose data survived on the drives
$tools->recoverOrphaned('media');

// Server-side zip of many objects
$job = $tools->createZipDownload(['bucket' => 'media', 'prefix' => '2026/']);   // ['download_url' => ..., 'expires_at' => ...]
$tools->downloadZip($job['download_url'], ['sink' => storage_path('app/export.zip')]);   // accepts the URL or the bare id
```

## ILM transitions — `ilm()`

Lifecycle rules move objects to tiers in the background. These calls control and inspect that process:

```php
$ilm = $admin->ilm();

$job = $ilm->runTransition(['bucket' => 'logs', 'prefix' => '2025/', 'tier' => 'COLD']);   // run now
$ilm->transitionJob($job['job_id']);
$ilm->cancelTransitionJob($job['job_id']);

$ilm->inspectReconcile($transactionId);
$ilm->applyReconcile($transactionId);
$ilm->inspectTransitionState(['bucket' => 'logs', 'object' => 'a.log', 'versionId' => 'null']);
$ilm->applyTransitionState(['bucket' => 'logs', 'object' => 'a.log', 'versionId' => 'null']);

$ilm->recoveryRecords();
$ilm->recoveryRecord($controlId);
$ilm->updateRecoveryRecord($controlId, [...]);
$ilm->downloadRecoveryExport($exportId);
```

## Not wrapped: batch jobs

RustFS 1.0.x registers MinIO's batch-job routes (`/v3/start-job`, `/v3/list-jobs`, `/v3/status-job`, `/v3/describe-job`, `/v3/cancel-job`) for client compatibility only. RustFS has no batch execution engine yet, so they always answer `NotImplemented` / `NoSuchJob`. Once a future RustFS version implements them, you can call them with `$admin->request()`.

Next: [Artisan commands](08-artisan-commands.md).
