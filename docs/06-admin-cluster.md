# 6. Admin: cluster operations

```php
$admin = RustFs::admin();
```

Methods that return `array` decode the JSON response. Methods typed `mixed` return the decoded JSON, a string for non-JSON responses, or `null` for empty ones. The [route map](admin-route-map.md) lists the exact endpoint behind each method.

## Server information & usage — `system()`

```php
$system = $admin->system();

$info = $system->info();
// mode ("online"), domain, region, deploymentID, buckets.count, objects.count, versions, deletemarkers,
// usage.size, services, backend, servers[] (state, endpoint, version, drives, mem_stats, ...), pools
$system->rawInfo();        // the full response, including RustFS's "admin_discovery" and "bitrotSelftest" sections

$system->storageInfo();    // drives with capacity and state, plus the backend layout
$system->dataUsageInfo();  // total_capacity, total_used_capacity, objects_total_count, buckets_usage, ...
                           // (computed by the background scanner, so it trails real time)
$system->prefixUsage('media');          // usage per prefix inside a bucket
$system->realtime(['types' => 'cpu,mem,disk']);
$system->capabilities();   // which optional admin features this server supports
$system->clusterSnapshot();
```

`info()` and `storageInfo()` unwrap the `{"info": {...}}` envelope RustFS puts around the MinIO-shaped document, so you get the document itself.

### Service control

```php
use Siberfx\RustFs\Admin\Enums\ServiceAction;

$system->restart();        // restarts every node
$system->stop();
$system->freeze();         // pause S3 traffic (e.g. for maintenance)
$system->unfreeze();
$system->service(ServiceAction::Restart);
$system->update();         // server self-update, where supported
$system->inspectData('media', 'path/to/object/xl.meta');   // raw on-disk data for support (archive bytes)
```

## Bucket quotas — `quotas()`

```php
$quotas = $admin->quotas();

$quotas->set('media', 50 * 1024 ** 3);       // hard limit in bytes. Returns ['bucket', 'quota', 'size', 'quota_type']
$quotas->get('media');                        // ['bucket' => 'media', 'quota' => 53687091200, 'size' => 0, 'quota_type' => 'HARD']
$quotas->stats('media');                      // ['quota_limit', 'current_usage', 'remaining_quota', 'usage_percentage']
$quotas->check('media', 2 * 1024 ** 3);       // ['allowed' => bool, ...]: would a 2 GiB PUT fit?
$quotas->clear('media');
```

Writes that exceed a hard quota are rejected by the server.

> Right after a bucket is created, RustFS may answer `503 authoritative bucket usage is not available yet` until its scanner has measured the bucket. The admin client retries 503s automatically (`admin_retries`, `admin_retry_delay_ms`), so these calls usually just take a few seconds the first time.

## Storage tiers — `tiers()`

A tier is a remote S3-compatible target (another RustFS, MinIO, AWS S3...) that lifecycle `Transitions` can move cold data to.

```php
$tiers = $admin->tiers();

$tiers->add([
    'Version' => 'v1',
    'Type' => 's3',
    'Name' => 'COLD',
    'S3' => [
        'Endpoint' => 'https://cold.example.com:9000',
        'AccessKey' => '...',
        'SecretKey' => '...',
        'Bucket' => 'cold-tier',
        'Prefix' => 'rustfs/',
        'Region' => 'us-east-1',
        'StorageClass' => 'STANDARD',
    ],
]);

$tiers->list();
$tiers->stats();                      // objects and bytes per tier
$tiers->verify('COLD');               // checks the tier is reachable with its stored credentials
$tiers->edit('COLD', ['access' => 'new-key', 'secret' => 'new-secret']);   // rotate credentials
$tiers->remove('COLD');
$tiers->remove('COLD', force: true);
$tiers->clear();                      // removes every tier (destructive)
```

Then reference the tier in a lifecycle rule: `'Transitions' => [['Days' => 90, 'StorageClass' => 'COLD']]` (see [Buckets → Lifecycle](03-buckets.md#lifecycle-ilm)).

## Pools, decommissioning & rebalancing — `pools()`

```php
$pools = $admin->pools();

$pools->list();       // [['id' => 0, 'cmdline' => '...', 'totalSize' => ..., 'usedSize' => ..., 'status' => ..., 'decommissionStatus' => ..., ...]]
$pools->status('http://node{1...4}/data{1...4}');   // a pool by its command line
$pools->status('0', byId: true);                     // ... or by index

// Drain a pool before removing its servers
$pools->decommission('0', byId: true);
$pools->decommissionStatus();
$pools->cancelDecommission('0', byId: true);
$pools->clearDecommission('0', byId: true);

// Spread data evenly after adding a pool
$pools->startRebalance();
$pools->rebalanceStatus();
$pools->stopRebalance();
```

## Healing — `heal()`

RustFS repairs erasure-coded data in the background. You can also start a heal for the whole cluster, a bucket or a prefix:

```php
$heal = $admin->heal();

$sequence = $heal->start('media', 'photos/2026/', [
    'recursive' => true,
    'dryRun' => false,
    'remove' => false,      // also remove dangling objects
    'scanMode' => 1,        // 1 = normal, 2 = deep (bitrot check)
]);
$token = $sequence['clientToken'] ?? null;

$heal->status($token, 'media', 'photos/2026/');   // poll with the token
$heal->stop('media');
$heal->start(forceStart: true);                    // whole cluster, restarting a running sequence

$heal->backgroundStatus();          // state, healQueueLength, healActiveTasks, coverage, ...
$heal->replacementRecoveryStatus(); // progress after a drive replacement
$heal->mrfResponsibilities();       // most-recently-failed queue ownership
```

## Server configuration — `config()`

RustFS stores runtime configuration as **sub-systems** with `key=value` pairs. On RustFS 1.0.x these sub-systems exist: `storage_class`, `scanner`, `heal`, `identity_openid`, `notify_*` and `audit_*`.

```php
$config = $admin->config();

$config->help();                         // every sub-system and its description
$config->help('scanner');                // the keys of one sub-system
$config->get('scanner');                 // 'scanner alert_excess_versions="100" cycle_max_duration="1800" ...'
$config->get('notify_webhook:primary');  // one named target

$applied = $config->set('scanner idle_mode=false');   // true = applied live, false = restart needed
$config->delete('notify_webhook:primary');

$config->history(10);                    // past changes
$config->restore($restoreId);
$config->clearHistory();                 // or clearHistory($restoreId)

$config->all();                          // the full config document
$config->replace($fullConfigText);

// Event notification and audit modules
$config->moduleSwitches();               // ['notify_enabled' => false, 'audit_enabled' => false, ...]
$config->updateModuleSwitches(notify: true);              // the other flag keeps its current value
$config->updateModuleSwitches(notify: true, audit: true);
```

## Diagnostics — `diagnostics()`

```php
$diag = $admin->diagnostics();

$diag->topLocks(['count' => 10]);
$diag->forceUnlock(['media/stuck-object']);
$diag->healthInfo();                     // full health report (OBD)
$diag->obdInfo();
$diag->log(['limit' => 100]);            // console log, as a PSR-7 response (ndjson)

use Siberfx\RustFs\Admin\Enums\SpeedtestType;
$diag->speedtest(SpeedtestType::Object, ['size' => 64 * 1024 * 1024, 'concurrent' => 16, 'duration' => '10s']);
$diag->speedtest(SpeedtestType::Drive);
$diag->speedtest(SpeedtestType::Net);
$diag->speedtest(SpeedtestType::Site);

$diag->scannerStatus();                  // enabled, freshness, metrics, cycle_schedule, ...
$diag->resetScannerCycleState();
$diag->resetScannerUsageState();
$diag->ilmExpiryStatus();

$diag->objectCacheStats();               // hits, lookups, entries, ...
$diag->flushObjectCache();
$diag->tlsStatus();
$diag->gatewayKeyInventory();
$diag->inspectArchive([...]);            // returns archive bytes
```

## Tracing & profiling — `profiling()`

```php
$profiling = $admin->profiling();

// Live request trace, streamed. Stop after 100 events
foreach ($profiling->traceEvents(['s3' => true, 'err' => true, 'threshold' => '100ms'], limit: 100) as $event) {
    // ['type' => ..., 'nodename' => ..., 'funcname' => 's3.PutObject', 'time' => ..., 'path' => ..., 'duration' => ...]
}
$response = $profiling->trace(['all' => true]);   // the raw streaming PSR-7 response

$profiling->status();                    // ['enabled' => ..., 'status' => ..., 'platform' => ..., 'message' => ...]
$profiling->start('cpu,mem');
$zip = $profiling->download();
$pprof = $profiling->profile(['seconds' => 30]);
```

> CPU/memory profiling is platform-dependent. `status()` explains what is available on your server.

## Plugins & extensions — `extensions()`

```php
$ext = $admin->extensions();

$ext->pluginCatalog();
$ext->plugins();                         // ['instances' => [...], 'truncated' => bool, 'next_marker' => ...]
$ext->plugin('my-plugin');
$ext->putPlugin('my-plugin', [...]);
$ext->deletePlugin('my-plugin');
$ext->extensionCatalog();
$ext->extensions();
```

Next: [Admin: security, replication & events](07-admin-security-replication.md).
