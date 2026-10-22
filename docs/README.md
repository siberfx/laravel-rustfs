# laravel-rustfs documentation

These guides cover every part of `siberfx/laravel-rustfs`. If you are new, start with the first one. The rest can be read in any order.

| # | Guide | What it covers |
| --- | --- | --- |
| 1 | [Installation & configuration](01-installation-and-configuration.md) | Requirements, install, `.env`, every config key, multiple connections, Laravel Herd |
| 2 | [Filesystem disk](02-filesystem-disk.md) | The `rustfs` disk driver, inherited options, public URLs, temporary URLs, RustFS extras on the disk |
| 3 | [Buckets](03-buckets.md) | Creating and deleting buckets, versioning, object lock, lifecycle, replication rules, encryption, policies, tags, CORS, event notifications |
| 4 | [Objects](04-objects.md) | Uploads (single, automatic multipart, client-driven multipart), downloads, listing, versions, deleting, tags, retention, legal hold, presigned URLs and forms, S3 Select |
| 5 | [Admin: identity & access](05-admin-iam.md) | Users, groups, canned policies, policy attachments, service accounts, your own account and MFA, IAM export/import |
| 6 | [Admin: cluster operations](06-admin-cluster.md) | Server info and usage, service control, quotas, tiers, pools and rebalancing, healing, server config, diagnostics, tracing, profiling, plugins |
| 7 | [Admin: security, replication & events](07-admin-security-replication.md) | KMS, bucket replication targets, site replication, notification and audit targets, bucket admin tools, ILM transitions |
| 8 | [Artisan commands](08-artisan-commands.md) | `rustfs:health`, `rustfs:info`, `rustfs:bucket`, `rustfs:user` |
| 9 | [Errors, testing & troubleshooting](09-testing-and-troubleshooting.md) | Exceptions, retries, faking RustFS in your own tests, the live test suite, common problems |
| — | [Admin API route map](admin-route-map.md) | Every admin method and the exact RustFS route it calls |

## How the package is organised

```
RustFs (facade)  ->  RustFsManager  ->  RustFsConnection (one per configured deployment)
                                          ├── s3()       Aws\S3\S3Client
                                          ├── buckets()  Siberfx\RustFs\S3\Buckets
                                          ├── objects()  Siberfx\RustFs\S3\Objects     (bound to one bucket)
                                          ├── admin()    Siberfx\RustFs\Admin\Admin    (21 resource groups)
                                          └── health()   Siberfx\RustFs\Health\Health

Storage::disk('...') with driver "rustfs"  ->  Siberfx\RustFs\Filesystem\RustFsFilesystemAdapter
```

- The **S3 side** (`buckets()`, `objects()`, the disk) runs through the official AWS SDK for PHP, set up for RustFS: path-style URLs, and flexible checksums only when an operation requires them.
- The **Admin side** (`admin()`) is a small Guzzle client. It signs every request with AWS Signature V4 (service `s3`) using the connection's credentials, sends plain JSON to `/rustfs/admin/...`, and decodes the JSON responses.
- The **health probes** (`health()`) are plain unauthenticated `GET` requests.

Every class and public method has PHPDoc in `src/`, which your IDE will show inline.
