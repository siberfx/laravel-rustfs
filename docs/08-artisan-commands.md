# 8. Artisan commands

Every command takes `--connection=<name>` to target a connection other than the default.

## `rustfs:health`

Checks the four unauthenticated probes. It exits with `0` when all of them pass, which makes it usable in deploy scripts and container health checks.

```console
$ php artisan rustfs:health

   INFO  RustFS http://127.0.0.1:9000.

  live ............................................................. OK
  ready ............................................................ OK
  cluster (write quorum) ........................................... OK
  cluster (read quorum) ............................................ OK
```

## `rustfs:info`

Shows the deployment summary from the Admin API (needs admin credentials).

```console
$ php artisan rustfs:info

  Mode ......................................................... online
  Region ............................................................ -
  Deployment ID .................. 797f7ed2-253b-46b5-bce9-77a1b78d7eed
  Buckets ........................................................... 3
  Objects ......................................................... 120
  Used ........................................................ 1.20 GiB

+----------+--------+---------------------------------+--------+
| Endpoint | State  | Version                         | Drives |
+----------+--------+---------------------------------+--------+
| :::9000  | online | 2026-10-03T07:07:41+08:00@1.0.1 | 1      |
+----------+--------+---------------------------------+--------+
```

`--json` prints the raw info document instead.

## `rustfs:bucket`

```bash
php artisan rustfs:bucket                                  # same as "list"
php artisan rustfs:bucket list
php artisan rustfs:bucket create media
php artisan rustfs:bucket create invoices --lock           # object lock (WORM). Implies versioning
php artisan rustfs:bucket create docs --versioned
php artisan rustfs:bucket info media                       # versioning, lock, encryption, lifecycle, policy, quota
php artisan rustfs:bucket versioning media on              # on|enable|enabled / off|suspend|suspended
php artisan rustfs:bucket quota media 10737418240          # set a hard quota, in bytes
php artisan rustfs:bucket quota media clear
php artisan rustfs:bucket quota media                      # show the quota
php artisan rustfs:bucket delete scratch                   # asks for confirmation when interactive
php artisan rustfs:bucket delete scratch --force           # also removes every object version
```

## `rustfs:user`

```bash
php artisan rustfs:user                                    # same as "list"
php artisan rustfs:user create alice --policy=readwrite    # generates and prints a 32-character secret
php artisan rustfs:user create bob --secret=chosen-secret --policy=readonly
php artisan rustfs:user info alice
php artisan rustfs:user disable alice
php artisan rustfs:user enable alice
php artisan rustfs:user policy alice --policy=readonly,diagnostics
php artisan rustfs:user delete alice
```

The generated secret is printed only once. Store it right away.

## Scheduling a health check

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('rustfs:health')->everyFiveMinutes()->emailOutputOnFailure('ops@example.com');
```

Next: [Errors, testing & troubleshooting](09-testing-and-troubleshooting.md).
