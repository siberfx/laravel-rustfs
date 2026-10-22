# Admin API route map

Every method of `RustFs::admin()` and the RustFS route it calls. Paths are relative to the admin prefix (`/rustfs/admin` by default), so `/v3/list-users` is sent as `/rustfs/admin/v3/list-users`. `{x}` marks a value that comes from a method argument and is percent-encoded per path segment.

This file is generated from `src/Admin/Resources`. Rows marked *(uses another method)* call one of the other methods in the same table.

Need a route that is not listed? Use `RustFs::admin()->request($method, $path, $query, $body)`, or `send()` for streams and binary downloads.

## `account()` — `Account`

| Method | HTTP | Route |
| --- | --- | --- |
| `info()` | GET | `/v3/accountinfo` |
| `self()` | GET | `/v3/account/info` |
| `changePassword()` | POST | `/v3/account/password` |
| `isAdmin()` | GET | `/v3/is-admin` |
| `mfaStatus()` | GET | `/v3/account/mfa` |
| `enrollMfa()` | POST | `/v3/account/mfa/enroll` |
| `activateMfa()` | POST | `/v3/account/mfa/activate` |
| `disableMfa()` | POST | `/v3/account/mfa/disable` |
| `regenerateRecoveryCodes()` | POST | `/v3/account/mfa/recovery-codes` |
| `mfaChallenge()` | GET | `/v3/mfa/challenge` |

## `buckets()` — `BucketAdmin`

| Method | HTTP | Route |
| --- | --- | --- |
| `exportMetadata()` | GET | `/v3/export-bucket-metadata` |
| `importMetadata()` | PUT | `/v3/import-bucket-metadata` |
| `durability()` | GET | `/v3/bucket-durability/{bucket}` |
| `setDurability()` | PUT | `/v3/bucket-durability/{bucket}` |
| `resetDurability()` | DELETE | `/v3/bucket-durability/{bucket}` |
| `migration()` | GET | `/v3/on-demand-migration/{bucket}` |
| `setMigration()` | PUT | `/v3/on-demand-migration/{bucket}` |
| `deleteMigration()` | DELETE | `/v3/on-demand-migration/{bucket}` |
| `migrationStatus()` | GET | `/v3/on-demand-migration/{bucket}/status` |
| `migrationBackfill()` | GET | `/v3/on-demand-migration/{bucket}/backfill` |
| `controlMigrationBackfill()` | POST | `/v3/on-demand-migration/{bucket}/backfill` |
| `recoverOrphaned()` | POST | `/v3/recover-orphaned-bucket/{bucket}` |
| `createZipDownload()` | POST | `/v3/object-zip-downloads` |
| `downloadZip()` | GET | `/v3/object-zip-downloads/{id}.zip` |

## `config()` — `Config`

| Method | HTTP | Route |
| --- | --- | --- |
| `get()` | GET | `/v3/get-config-kv` |
| `set()` | PUT | `/v3/set-config-kv` |
| `delete()` | DELETE | `/v3/del-config-kv` |
| `help()` | GET | `/v3/help-config-kv` |
| `all()` | GET | `/v3/config` |
| `replace()` | PUT | `/v3/config` |
| `history()` | GET | `/v3/list-config-history-kv` |
| `clearHistory()` | DELETE | `/v3/clear-config-history-kv` |
| `restore()` | PUT | `/v3/restore-config-history-kv` |
| `moduleSwitches()` | GET | `/v3/module-switches` |
| `updateModuleSwitches()` | PUT | `/v3/module-switches` |

## `diagnostics()` — `Diagnostics`

| Method | HTTP | Route |
| --- | --- | --- |
| `topLocks()` | GET | `/v3/top/locks` |
| `forceUnlock()` | POST | `/v3/force-unlock` |
| `healthInfo()` | GET | `/v3/healthinfo` |
| `obdInfo()` | GET | `/v3/obdinfo` |
| `log()` | GET | `/v3/log` |
| `speedtest()` | POST | `/v3/speedtest/{type}` |
| `scannerStatus()` | GET | `/v3/scanner/status` |
| `resetScannerCycleState()` | POST | `/v3/scanner/cycle-state/reset` |
| `resetScannerUsageState()` | POST | `/v3/scanner/usage-state/reset` |
| `scannerRecoveryIntent()` | GET | `/v3/scanner/usage-state/recovery-intents/{intentId}` |
| `ilmExpiryStatus()` | GET | `/v3/ilm/expiry/status` |
| `objectCacheStats()` | GET | `/v3/object-data-cache/stats` |
| `flushObjectCache()` | POST | `/v3/object-data-cache/flush` |
| `gatewayKeyInventory()` | GET | `/v3/gateway-key-inventory` |
| `tlsStatus()` | GET | `/debug/tls/status` |
| `inspectArchive()` | POST | `/v4/inspect/archive` |

## `extensions()` — `Extensions`

| Method | HTTP | Route |
| --- | --- | --- |
| `pluginCatalog()` | GET | `/v4/plugins/catalog` |
| `plugins()` | GET | `/v4/plugins/instances` |
| `plugin()` | GET | `/v4/plugins/instances/{id}` |
| `putPlugin()` | PUT | `/v4/plugins/instances/{id}` |
| `deletePlugin()` | DELETE | `/v4/plugins/instances/{id}` |
| `extensionCatalog()` | GET | `/v4/extensions/catalog` |
| `extensions()` | GET | `/v4/extensions/instances` |

## `groups()` — `Groups`

| Method | HTTP | Route |
| --- | --- | --- |
| `list()` | GET | `/v3/groups` |
| `info()` | GET | `/v3/group` |
| `create()` | | *(uses another method)* |
| `addMembers()` | | *(uses another method)* |
| `removeMembers()` | | *(uses another method)* |
| `delete()` | DELETE | `/v3/group/{group}` |
| `setStatus()` | PUT | `/v3/set-group-status` |
| `enable()` | | *(uses another method)* |
| `disable()` | | *(uses another method)* |
| `setPolicy()` | PUT | `/v3/set-user-or-group-policy` |

## `heal()` — `Heal`

| Method | HTTP | Route |
| --- | --- | --- |
| `start()` | POST | `/v3/heal/`, `/v3/heal/{bucket}` or `/v3/heal/{bucket}/{prefix}` |
| `status()` | | *(uses another method)* |
| `stop()` | | *(uses another method)* |
| `backgroundStatus()` | POST | `/v3/background-heal/status` |
| `mrfResponsibilities()` | GET | `/v4/heal/mrf/responsibilities` |
| `mrfResponsibilitiesAction()` | POST | `/v4/heal/mrf/responsibilities/actions` |
| `replacementRecoveryStatus()` | GET | `/v4/heal/replacement-recovery` |

## `iam()` — `Iam`

| Method | HTTP | Route |
| --- | --- | --- |
| `export()` | GET | `/v3/export-iam` |
| `import()` | PUT | `/v3/import-iam` |

## `ilm()` — `Ilm`

| Method | HTTP | Route |
| --- | --- | --- |
| `runTransition()` | POST | `/v3/ilm/transition/run` |
| `transitionJob()` | GET | `/v3/ilm/transition/jobs/{jobId}` |
| `cancelTransitionJob()` | DELETE | `/v3/ilm/transition/jobs/{jobId}` |
| `inspectReconcile()` | GET | `/v3/ilm/transition/reconcile/{transactionId}` |
| `applyReconcile()` | POST | `/v3/ilm/transition/reconcile/{transactionId}` |
| `recoveryRecords()` | GET | `/v3/ilm/recovery/records` |
| `recoveryRecord()` | GET | `/v3/ilm/recovery/records/{controlId}` |
| `updateRecoveryRecord()` | POST | `/v3/ilm/recovery/records/{controlId}` |
| `downloadRecoveryExport()` | GET | `/v3/ilm/recovery/exports/{exportId}` |
| `inspectTransitionState()` | GET | `/v3/ilm/transition/state/reconcile` |
| `applyTransitionState()` | POST | `/v3/ilm/transition/state/reconcile` |

## `kms()` — `Kms`

| Method | HTTP | Route |
| --- | --- | --- |
| `status()` | GET | `/v3/kms/status` |
| `serviceStatus()` | GET | `/v3/kms/service-status` |
| `config()` | GET | `/v3/kms/config` |
| `configure()` | POST | `/v3/kms/configure` |
| `reconfigure()` | POST | `/v3/kms/reconfigure` |
| `start()` | POST | `/v3/kms/start` |
| `stop()` | POST | `/v3/kms/stop` |
| `reload()` | POST | `/v3/kms/reload` |
| `clearCache()` | POST | `/v3/kms/clear-cache` |
| `createKey()` | POST | `/v3/kms/keys` |
| `listKeys()` | GET | `/v3/kms/keys` |
| `describeKey()` | GET | `/v3/kms/keys/{keyId}` |
| `deleteKey()` | DELETE | `/v3/kms/keys/delete` |
| `cancelKeyDeletion()` | POST | `/v3/kms/keys/cancel-deletion` |
| `enableKey()` | POST | `/v3/kms/keys/enable` |
| `disableKey()` | POST | `/v3/kms/keys/disable` |
| `rotateKey()` | POST | `/v3/kms/keys/rotate` |
| `updateKeyDescription()` | POST | `/v3/kms/keys/update-description` |
| `tagKey()` | POST | `/v3/kms/keys/tag` |
| `untagKey()` | POST | `/v3/kms/keys/untag` |
| `generateDataKey()` | POST | `/v3/kms/generate-data-key` |
| `startRekey()` | POST | `/v3/kms/keys/rekey` |
| `rekeyStatus()` | GET | `/v3/kms/keys/rekey/status` |
| `cancelRekey()` | POST | `/v3/kms/keys/rekey/cancel` |
| `backupStatus()` | GET | `/v3/kms/backup` |
| `backup()` | POST | `/v3/kms/backup` |
| `restore()` | POST | `/v3/kms/restore` or `/v3/kms/restore/dry-run` |
| `abortRestore()` | POST | `/v3/kms/restore/abort` |

## `policies()` — `Policies`

| Method | HTTP | Route |
| --- | --- | --- |
| `list()` | GET | `/v3/list-canned-policies` |
| `info()` | GET | `/v3/info-canned-policy` |
| `create()` | PUT | `/v3/add-canned-policy` |
| `delete()` | DELETE | `/v3/remove-canned-policy` |
| `set()` | PUT | `/v3/set-user-or-group-policy` |
| `attach()` | POST | `/v3/idp/builtin/policy/attach` |
| `detach()` | POST | `/v3/idp/builtin/policy/detach` |
| `entities()` | GET | `/v3/idp/builtin/policy-entities` |

## `pools()` — `Pools`

| Method | HTTP | Route |
| --- | --- | --- |
| `list()` | GET | `/v3/pools/list` |
| `status()` | GET | `/v3/pools/status` |
| `decommissionStatus()` | GET | `/v3/decommission/status` |
| `decommission()` | POST | `/v3/pools/decommission` |
| `cancelDecommission()` | POST | `/v3/pools/cancel` |
| `clearDecommission()` | POST | `/v3/pools/clear` |
| `startRebalance()` | POST | `/v3/rebalance/start` |
| `rebalanceStatus()` | GET | `/v3/rebalance/status` |
| `stopRebalance()` | POST | `/v3/rebalance/stop` |

## `profiling()` — `Profiling`

| Method | HTTP | Route |
| --- | --- | --- |
| `trace()` | GET | `/v3/trace` |
| `traceEvents()` | | *(uses another method)* |
| `profile()` | GET | `/debug/pprof/profile` |
| `status()` | GET | `/debug/pprof/status` |
| `start()` | POST | `/v3/profiling/start` |
| `download()` | GET | `/v3/profiling/download` |
| `control()` | POST | `/v3/profile` |

## `quotas()` — `Quotas`

| Method | HTTP | Route |
| --- | --- | --- |
| `get()` | GET | `/v3/quota/{bucket}` |
| `set()` | PUT | `/v3/quota/{bucket}` |
| `clear()` | DELETE | `/v3/quota/{bucket}` |
| `stats()` | GET | `/v3/quota-stats/{bucket}` |
| `check()` | POST | `/v3/quota-check/{bucket}` |
| `migrateProtocol()` | POST | `/v3/quota-migration` |

## `replication()` — `Replication`

| Method | HTTP | Route |
| --- | --- | --- |
| `remoteTargets()` | GET | `/v3/list-remote-targets` |
| `setRemoteTarget()` | PUT | `/v3/set-remote-target` |
| `removeRemoteTarget()` | DELETE | `/v3/remove-remote-target` |
| `metrics()` | GET | `/v3/replicationmetrics` |
| `diff()` | POST | `/v3/replication/diff` |
| `mrf()` | GET | `/v3/replication/mrf` |

## `serviceAccounts()` — `ServiceAccounts`

| Method | HTTP | Route |
| --- | --- | --- |
| `create()` | PUT | `/v3/add-service-account` |
| `update()` | POST | `/v3/update-service-account` |
| `enable()` | | *(uses another method)* |
| `disable()` | | *(uses another method)* |
| `info()` | GET | `/v3/info-service-account` |
| `list()` | GET | `/v3/list-service-accounts` |
| `delete()` | DELETE | `/v3/delete-service-account` |
| `accessKeyInfo()` | GET | `/v3/info-access-key` |
| `temporaryAccountInfo()` | GET | `/v3/temporary-account-info` |
| `listBulk()` | GET | `/v3/list-access-keys-bulk` |

## `siteReplication()` — `SiteReplication`

| Method | HTTP | Route |
| --- | --- | --- |
| `add()` | PUT | `/v3/site-replication/add` |
| `remove()` | PUT | `/v3/site-replication/remove` |
| `info()` | GET | `/v3/site-replication/info` |
| `metaInfo()` | GET | `/v3/site-replication/metainfo` |
| `status()` | GET | `/v3/site-replication/status` |
| `edit()` | PUT | `/v3/site-replication/edit` |
| `resync()` | PUT | `/v3/site-replication/resync/op` |
| `repair()` | PUT | `/v3/site-replication/repair` |
| `repairStatus()` | GET | `/v3/site-replication/repair/status` |
| `editState()` | PUT | `/v3/site-replication/state/edit` |
| `netperf()` | POST | `/v3/site-replication/netperf` |

## `system()` — `System`

| Method | HTTP | Route |
| --- | --- | --- |
| `info()` | | *(uses another method)* |
| `rawInfo()` | GET | `/v3/info` |
| `storageInfo()` | GET | `/v3/storageinfo` |
| `dataUsageInfo()` | GET | `/v3/datausageinfo` |
| `prefixUsage()` | GET | `/v3/usage/{bucket}` |
| `realtime()` | GET | `/v3/realtime` |
| `capabilities()` | GET | `/v4/runtime/capabilities` |
| `clusterSnapshot()` | GET | `/v4/cluster/snapshot` |
| `service()` | POST | `/v3/service` |
| `restart()` | | *(uses another method)* |
| `stop()` | | *(uses another method)* |
| `freeze()` | | *(uses another method)* |
| `unfreeze()` | | *(uses another method)* |
| `update()` | POST | `/v3/update` |
| `inspectData()` | GET | `/v3/inspect-data` |

## `notifications() / audit()` — `Targets`

| Method | HTTP | Route |
| --- | --- | --- |
| `list()` | GET | `{base}/list` |
| `set()` | PUT | `{base}/' . {this}->subsystem({type}` |
| `remove()` | DELETE | `{base}/' . {this}->subsystem({type}` |
| `arns()` | GET | `/v3/target/arns` |
| `subscriptions()` | GET | `/v3/target/' . self::prefixed('notify_` |
| `subsystem()` | | *(uses another method)* |

`{base}` is `/v3/target` for `notifications()` and `/v3/audit/target` for `audit()`.

## `tiers()` — `Tiers`

| Method | HTTP | Route |
| --- | --- | --- |
| `list()` | GET | `/v3/tier` |
| `stats()` | GET | `/v3/tier-stats` |
| `verify()` | GET | `/v3/tier/{name}` |
| `add()` | PUT | `/v3/tier` |
| `edit()` | POST | `/v3/tier/{name}` |
| `remove()` | DELETE | `/v3/tier/{name}` |
| `clear()` | POST | `/v3/tier/clear` |

## `users()` — `Users`

| Method | HTTP | Route |
| --- | --- | --- |
| `list()` | GET | `/v3/list-users` |
| `info()` | GET | `/v3/user-info` |
| `exists()` | | *(uses another method)* |
| `create()` | PUT | `/v3/add-user`, then `/v3/set-user-or-group-policy` when a policy is given |
| `delete()` | DELETE | `/v3/remove-user` |
| `setStatus()` | PUT | `/v3/set-user-status` |
| `enable()` | | *(uses another method)* |
| `disable()` | | *(uses another method)* |
| `setSecretKey()` | PUT | `/v3/set-user-secret-key` |
| `setPolicy()` | PUT | `/v3/set-user-or-group-policy` |
| `mfaStatus()` | GET | `/v3/user/mfa` |
| `resetMfa()` | DELETE | `/v3/user/mfa` |

## Unauthenticated health probes — `RustFs::health()`

| Method | HTTP | Route |
| --- | --- | --- |
| `live()` | GET | `/health/live` |
| `ready()` | GET | `/health/ready` |
| `cluster()` | GET | `/minio/health/cluster` |
| `clusterRead()` | GET | `/minio/health/cluster/read` |
| `details()` | GET | `/health` |

These paths are absolute: no admin prefix and no signature.
