# 5. Admin: identity & access

The Admin API needs admin-level credentials. That means the root credentials, or a user whose policy allows the `admin:*` actions involved. Every call is signed with SigV4 using the connection's key and secret.

```php
$admin = RustFs::admin();
$admin->account()->isAdmin();   // true if these credentials can call admin APIs
```

## Users

```php
use Siberfx\RustFs\Admin\Enums\AccountStatus;

$users = $admin->users();

$users->create('alice', 'a-long-secret-key', policy: 'readwrite');   // also updates an existing user (see the note below)
$users->create('bob', 'another-secret', status: AccountStatus::Disabled);

$users->list();                   // ['alice' => ['status' => 'enabled', 'policyName' => 'readwrite', 'memberOf' => ['developers'], 'updatedAt' => ...], ...]
$users->list(bucket: 'media');    // only users with access to the bucket
$users->info('alice');            // same shape as one list() entry
$users->exists('alice');

$users->disable('alice');
$users->enable('alice');
$users->setStatus('alice', AccountStatus::Disabled);

$users->setSecretKey('alice', 'rotated-secret');   // keeps status, policies and groups
$users->setPolicy('alice', 'readonly,diagnostics');  // replaces the user's policies (comma-separated list)

$users->mfaStatus('alice');
$users->resetMfa('alice');       // an admin removes a user's MFA (lost device)

$users->delete('alice');
```

`policyName` and `memberOf` only appear once the user has a policy or a group.

> **Note:** RustFS 1.0.x silently ignores the `policy` field of its `add-user` endpoint. `create(..., policy: ...)` works around this by attaching the policy in a second call (`set-user-or-group-policy`). The live test suite checks this.

RustFS rejects an empty secret key and access keys that contain spaces. Those calls throw `AdminApiException` with status `400`.

## Groups

```php
$groups = $admin->groups();

$groups->create('developers', ['alice', 'bob']);   // a group exists once it has members
$groups->addMembers('developers', ['carol']);
$groups->removeMembers('developers', ['bob']);
$groups->list();                                   // ['developers', ...]
$groups->info('developers');                       // ['name' => ..., 'status' => ..., 'members' => [...], 'policy' => ...]

$groups->setPolicy('developers', 'readwrite');
$groups->disable('developers');                    // members lose the group's permissions
$groups->enable('developers');

$groups->removeMembers('developers', ['alice', 'carol']);
$groups->delete('developers');                     // must be empty, otherwise 400 "group is not empty"
```

## Canned policies

```php
$policies = $admin->policies();

$policies->create('media-readonly', [
    'Version' => '2012-10-17',
    'Statement' => [[
        'Effect' => 'Allow',
        'Action' => ['s3:GetObject', 's3:ListBucket'],
        'Resource' => ['arn:aws:s3:::media', 'arn:aws:s3:::media/*'],
    ]],
]);   // also accepts a JSON string; replaces a policy with the same name

$policies->list();                 // ['readonly' => [...document...], 'media-readonly' => [...], ...]
$policies->list(bucket: 'media');  // only policies that mention the bucket
$policies->info('media-readonly');
$policies->delete('media-readonly');
```

RustFS 1.0.x ships these built-in policies: `readonly`, `readwrite`, `writeonly`, `diagnostics`, `consoleAdmin`, `KMSAuditor`, `KMSKeyUser` and `KMSKeyAdministrator`.

### Attaching policies

There are two styles:

```php
// Replace: the user/group gets exactly these policies
$policies->set('readonly,media-readonly', 'alice');
$policies->set('readwrite', 'developers', isGroup: true);

// Add or remove: keep what is already attached
$policies->attach(['media-readonly'], user: 'alice');
$policies->detach(['media-readonly'], user: 'alice');
$policies->attach(['readwrite'], group: 'developers');

// Who has what
$policies->entities(users: ['alice'], policies: ['readwrite']);
```

## Service accounts (access keys)

A service account is an extra access key/secret pair that belongs to a parent user. It can be narrowed with a session policy and can expire, which makes it a good fit for CI jobs, applications and integrations.

```php
$accounts = $admin->serviceAccounts();

$creds = $accounts->create(
    targetUser: 'alice',              // defaults to the caller
    name: 'ci-deploy',
    description: 'GitHub Actions deploys',
    expiration: now()->addMonths(3),
    policy: [                          // optional: narrower than alice's own permissions
        'Version' => '2012-10-17',
        'Statement' => [['Effect' => 'Allow', 'Action' => ['s3:PutObject'], 'Resource' => ['arn:aws:s3:::releases/*']]],
    ],
);
// ['accessKey' => 'XXXXXXXX', 'secretKey' => '...'] (plus 'expiration' when one was set)
// The secret is only returned here. Store it now.

// Or choose the keys yourself
$accounts->create(targetUser: 'alice', accessKey: 'app-media', secretKey: 'a-long-secret-key');

$accounts->list('alice');                 // [['accessKey' => ..., 'parentUser' => 'alice', 'accountStatus' => 'on', 'name' => ..., 'expiration' => ..., 'impliedPolicy' => true], ...]
$accounts->info('app-media');
$accounts->update('app-media', newDescription: 'Media service', newExpiration: now()->addYear());
$accounts->update('app-media', newSecretKey: 'rotated-secret');
$accounts->disable('app-media');          // newStatus "off"
$accounts->enable('app-media');
$accounts->delete('app-media');

$accounts->accessKeyInfo('AKIA...');      // works for any key: user, service account or STS
$accounts->temporaryAccountInfo('STSKEY');
$accounts->listBulk(['alice', 'bob']);    // keys of several users at once
$accounts->listBulk(all: true, listType: 'svcacc-only');
```

## Your own account

These calls act on the identity whose credentials the connection uses:

```php
$account = $admin->account();

$account->info();          // ['account_name' => ..., 'server' => ..., 'policy' => ..., 'buckets' => [...]]: buckets you can access, with rights and usage
$account->self();          // ['access_key' => ..., 'identity_type' => ..., 'is_admin' => ..., 'status' => ..., 'member_of' => ..., 'policies' => ..., 'mfa' => ...]
$account->isAdmin();
$account->changePassword('current-secret', 'new-secret');

// TOTP multi-factor authentication
$account->mfaStatus();
$enrollment = $account->enrollMfa();        // secret / provisioning URI for an authenticator app
$account->activateMfa(['code' => '123456']);
$account->regenerateRecoveryCodes();
$account->disableMfa(['code' => '123456']);
$account->mfaChallenge();
```

> The exact request fields for MFA enrollment, activation and disabling are defined by RustFS. The methods pass `$payload` through as JSON, so check your server version's console/API for the fields it expects.

## IAM backup & restore

```php
$zip = $admin->iam()->export();                  // users, groups, policies, service accounts and mappings as a zip
Storage::disk('backups')->put('iam/' . now()->format('Y-m-d') . '.zip', $zip);

$admin->iam()->import(Storage::disk('backups')->get('iam/2026-10-23.zip'));
```

Next: [Admin: cluster operations](06-admin-cluster.md).
