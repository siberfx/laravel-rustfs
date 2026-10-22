# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-23

First release. Requires PHP 8.4.27+ and Laravel 13+. Verified against RustFS 1.0.1 (Laravel Herd) and the `rustfs/rustfs` Docker image.

### Added

- **Connections**: the `RustFs` facade and `RustFsManager` with any number of named connections. `ConnectionConfig` holds the immutable settings for each.
- **S3 buckets** (`Buckets`): list/exists/create (with object lock or versioning)/delete (optionally forced)/empty, location, versioning, object lock default retention, lifecycle, replication, default encryption (SSE-S3/SSE-KMS), bucket policy with a `makePublic()` helper, tags, CORS, and event notifications with an `addNotification()` helper. Reads of settings that were never configured return `null` / `[]`.
- **S3 objects** (`Objects`): `put`, `upload` (automatic parallel multipart), `copy`, `get`/`stream`/`download`, `head`/`exists`, lazy `list` and `versions` generators, `delete`, `deleteMany` (batches of 1000), `deletePrefix`, tags, retention, legal hold, presigned GET/PUT/part URLs, presigned POST forms, low-level multipart, S3 Select (`select`, `selectRows`) and `restore`.
- **Admin API client** (`AdminClient`): SigV4 (`s3` service) signing, plain-JSON `/rustfs/admin` prefix, repeated-key query strings, JSON/binary/text response decoding, `AdminApiException` with status, error code and request id parsed from XML or JSON errors, and 503 retries with backoff and `Retry-After` support.
- **Admin resources** (21 groups, more than 200 methods): `users`, `groups`, `policies`, `serviceAccounts`, `account` (MFA, password, is-admin), `iam` (export/import), `system` (info, storage, usage, realtime, capabilities, service control), `quotas`, `tiers`, `pools` (decommission, rebalance), `heal`, `kms` (service, keys, rotation, re-key, backup/restore), `replication`, `siteReplication`, `config` (KV, history, module switches), `buckets` (metadata export/import, durability, on-demand migration, orphan recovery, zip downloads), `ilm`, `diagnostics`, `profiling` (streamed trace events, pprof), `extensions`, `notifications`, `audit`. `request()` / `send()` reach any other route.
- **Health probes** (`Health`): live, ready, cluster write and read quorum, details.
- **`rustfs` filesystem disk**: built on Laravel's S3 driver with defaults from the connection. `RustFsFilesystemAdapter` adds `objects()`, `buckets()`, `key()`, `setTags()`, `getTags()` and `versions()`.
- **Artisan commands**: `rustfs:health`, `rustfs:info`, `rustfs:bucket`, `rustfs:user`.
- **Documentation**: nine guides in `docs/` plus a generated admin route map.
- **Tests**: Pest unit and feature suites (no server needed) and an opt-in live suite (`--group=live`) that runs against a real RustFS in CI.

### RustFS behaviours handled

- `add-user` ignores its `policy` field, so `Users::create()` attaches the policy explicitly.
- `/v3/info` and `/v3/storageinfo` wrap their data in an `info` envelope, which `info()` and `storageInfo()` unwrap.
- Notification and audit target types are config sub-systems (`notify_webhook`, `audit_kafka`, ...), so short names get the right prefix.
- Module switch updates must carry both flags, so the flag you leave out keeps its current value.

[1.0.0]: https://github.com/siberfx/laravel-rustfs/releases/tag/1.0.0
