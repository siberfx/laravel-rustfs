<?php

declare(strict_types=1);

/*
 * End-to-end tests against a real RustFS server. Excluded by default; run with:
 *
 *   RUSTFS_LIVE_ENDPOINT=http://127.0.0.1:9000 RUSTFS_LIVE_KEY=rustfsadmin RUSTFS_LIVE_SECRET=rustfsadmin \
 *       vendor/bin/pest --group=live
 */

use Aws\S3\Exception\S3Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Siberfx\RustFs\Admin\Enums\AccountStatus;
use Siberfx\RustFs\Exceptions\AdminApiException;
use Siberfx\RustFs\Facades\RustFs;
use Siberfx\RustFs\RustFsConnection;

pest()->group('live');

beforeEach(function () {
    $endpoint = getenv('RUSTFS_LIVE_ENDPOINT');
    if (! $endpoint) {
        $this->markTestSkipped('Set RUSTFS_LIVE_ENDPOINT to run live tests.');
    }

    config()->set('rustfs.connections.default', [
        'endpoint' => $endpoint,
        'key' => getenv('RUSTFS_LIVE_KEY') ?: 'rustfsadmin',
        'secret' => getenv('RUSTFS_LIVE_SECRET') ?: 'rustfsadmin',
        'region' => 'us-east-1',
    ]);
    RustFs::forget();

    $this->bucket = 'pest-' . strtolower(Str::random(8));
    $this->rustfs = RustFs::connection();
});

afterEach(function () {
    if (isset($this->bucket) && $this->rustfs instanceof RustFsConnection && $this->rustfs->buckets()->exists($this->bucket)) {
        $this->rustfs->buckets()->delete($this->bucket, force: true);
    }
});

it('reports healthy probes', function () {
    expect($this->rustfs->health()->live())->toBeTrue()
        ->and($this->rustfs->health()->ready())->toBeTrue();
});

it('reads server info through the signed admin API', function () {
    $admin = $this->rustfs->admin();

    expect($admin->system()->info())->toHaveKeys(['mode', 'deploymentID', 'servers'])
        ->and($admin->system()->storageInfo())->toBeArray()
        ->and($admin->account()->isAdmin())->toBeTrue();
});

it('runs a bucket and object round trip with versioning, tags and presigned URLs', function () {
    $buckets = $this->rustfs->buckets();
    $buckets->create($this->bucket, versioning: true);
    expect($buckets->exists($this->bucket))->toBeTrue()
        ->and($buckets->versioning($this->bucket))->toBe('Enabled');

    $objects = $this->rustfs->objects($this->bucket);
    $v1 = $objects->put('docs/a.txt', 'one', ['ContentType' => 'text/plain', 'Metadata' => ['owner' => 'pest']]);
    $objects->put('docs/a.txt', 'two');

    expect($objects->get('docs/a.txt'))->toBe('two')
        ->and($objects->get('docs/a.txt', $v1['VersionId']))->toBe('one')
        ->and(iterator_to_array($objects->versions('docs/'), false))->toHaveCount(2)
        ->and($objects->head('docs/a.txt', $v1['VersionId'])['Metadata'])->toBe(['owner' => 'pest']);

    $objects->putTags('docs/a.txt', ['env' => 'test']);
    expect($objects->tags('docs/a.txt'))->toBe(['env' => 'test']);

    $buckets->putTags($this->bucket, ['team' => 'web']);
    expect($buckets->tags($this->bucket))->toBe(['team' => 'web']);

    $url = $objects->temporaryUrl('docs/a.txt', '+2 minutes');
    expect(file_get_contents($url))->toBe('two');

    $upload = $objects->temporaryUploadUrl('docs/b.txt', '+2 minutes');
    $context = stream_context_create(['http' => ['method' => 'PUT', 'content' => 'via-presign', 'header' => "Content-Type: application/octet-stream\r\n"]]);
    file_get_contents($upload, false, $context);
    expect($objects->get('docs/b.txt'))->toBe('via-presign');

    $objects->copy('docs/b.txt', 'docs/c.txt');
    expect($objects->exists('docs/c.txt'))->toBeTrue()
        ->and($objects->deletePrefix('docs/'))->toBe(3);
});

it('uploads multipart objects', function () {
    $this->rustfs->buckets()->create($this->bucket);
    $objects = $this->rustfs->objects($this->bucket);

    $payload = random_bytes(6 * 1024 * 1024);
    $objects->upload('big.bin', $payload, multipartThreshold: 5 * 1024 * 1024, partSize: 5 * 1024 * 1024);

    expect((int) $objects->head('big.bin')['ContentLength'])->toBe(strlen($payload))
        ->and(md5($objects->get('big.bin')))->toBe(md5($payload));
});

it('configures lifecycle, CORS and bucket policy', function () {
    $buckets = $this->rustfs->buckets();
    $buckets->create($this->bucket);

    $buckets->putLifecycle($this->bucket, [['ID' => 'tmp', 'Status' => 'Enabled', 'Filter' => ['Prefix' => 'tmp/'], 'Expiration' => ['Days' => 1]]]);
    expect($buckets->lifecycle($this->bucket)[0]['ID'])->toBe('tmp');

    $buckets->putCors($this->bucket, [['AllowedMethods' => ['GET'], 'AllowedOrigins' => ['*']]]);
    expect($buckets->cors($this->bucket)[0]['AllowedMethods'])->toBe(['GET']);

    $buckets->makePublic($this->bucket, 'public/');
    expect($buckets->policy($this->bucket)['Statement'][0]['Action'])->toContain('s3:GetObject');

    $this->rustfs->objects($this->bucket)->put('public/hello.txt', 'hi');
    expect(file_get_contents($this->rustfs->config()->endpoint . '/' . $this->bucket . '/public/hello.txt'))->toBe('hi');

    $buckets->deletePolicy($this->bucket);
    $buckets->deleteLifecycle($this->bucket);
    expect($buckets->policy($this->bucket))->toBeNull()
        ->and($buckets->lifecycle($this->bucket))->toBe([]);
});

it('enforces object lock retention', function () {
    $buckets = $this->rustfs->buckets();
    $buckets->create($this->bucket, objectLock: true);
    $buckets->putObjectLock($this->bucket, 'GOVERNANCE', days: 1);

    expect($buckets->objectLock($this->bucket)['Rule']['DefaultRetention']['Mode'])->toBe('GOVERNANCE');

    $objects = $this->rustfs->objects($this->bucket);
    $version = $objects->put('locked.txt', 'worm')['VersionId'];
    expect($objects->retention('locked.txt', $version)['Mode'])->toBe('GOVERNANCE');

    $objects->putLegalHold('locked.txt', true, $version);
    expect($objects->legalHold('locked.txt', $version))->toBeTrue();
    $objects->putLegalHold('locked.txt', false, $version);

    expect(fn () => $objects->delete('locked.txt', $version))->toThrow(S3Exception::class);
    $objects->delete('locked.txt', $version, bypassGovernanceRetention: true);
    $buckets->delete($this->bucket, force: true);
});

it('manages IAM users, groups, policies and service accounts', function () {
    $admin = $this->rustfs->admin();
    $user = 'pest-' . strtolower(Str::random(6));
    $group = $user . '-grp';
    $policy = $user . '-ro';

    try {
        $admin->policies()->create($policy, [
            'Version' => '2012-10-17',
            'Statement' => [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Resource' => ['arn:aws:s3:::*']]],
        ]);
        expect($admin->policies()->list())->toHaveKey($policy);

        $admin->users()->create($user, 'pest-secret-' . Str::random(12), 'readonly');
        expect($admin->users()->exists($user))->toBeTrue()
            ->and($admin->users()->info($user)['policyName'] ?? null)->toBe('readonly');

        $admin->users()->disable($user);
        expect($admin->users()->info($user)['status'])->toBe(AccountStatus::Disabled->value);
        $admin->users()->enable($user);

        $admin->policies()->attach([$policy], user: $user);
        expect($admin->users()->info($user)['policyName'])->toContain($policy);

        $admin->groups()->addMembers($group, [$user]);
        expect($admin->groups()->list())->toContain($group)
            ->and($admin->groups()->info($group)['members'])->toBe([$user])
            ->and(fn () => $admin->groups()->delete($group))->toThrow(AdminApiException::class, 'group is not empty');

        $credentials = $admin->serviceAccounts()->create(targetUser: $user, name: 'pest');
        expect($credentials['accessKey'])->not->toBeEmpty()
            ->and(array_column($admin->serviceAccounts()->list($user), 'accessKey'))->toContain($credentials['accessKey']);
        $admin->serviceAccounts()->delete($credentials['accessKey']);
    } finally {
        rescue(fn () => $admin->groups()->removeMembers($group, [$user]), report: false);
        rescue(fn () => $admin->groups()->delete($group), report: false);
        rescue(fn () => $admin->users()->delete($user), report: false);
        rescue(fn () => $admin->policies()->delete($policy), report: false);
    }

    expect($admin->users()->exists($user))->toBeFalse();
});

it('ignores object ACLs: only bucket policies grant anonymous reads', function () {
    $this->rustfs->buckets()->create($this->bucket);
    $objects = $this->rustfs->objects($this->bucket);
    $objects->put('acl.txt', 'hi', ['ACL' => 'public-read']);
    $url = $this->rustfs->config()->endpoint . '/' . $this->bucket . '/acl.txt';

    expect(get_headers($url)[0])->toContain('403');

    $this->rustfs->buckets()->makePublic($this->bucket);
    expect(get_headers($url)[0])->toContain('200');
});

it('exports IAM as a zip archive', function () {
    expect(substr($this->rustfs->admin()->iam()->export(), 0, 2))->toBe('PK');
});

it('sets bucket quotas', function () {
    $this->rustfs->buckets()->create($this->bucket);
    $quotas = $this->rustfs->admin()->quotas();

    $quotas->set($this->bucket, 10 * 1024 * 1024);
    expect((int) $quotas->get($this->bucket)['quota'])->toBe(10 * 1024 * 1024);

    $quotas->clear($this->bucket);
});

it('serves the rustfs filesystem disk', function () {
    $this->rustfs->buckets()->create($this->bucket);
    config()->set('filesystems.disks.live', ['driver' => 'rustfs', 'bucket' => $this->bucket, 'root' => 'app']);
    $disk = Storage::disk('live');

    $disk->put('notes/hello.txt', 'hello disk');
    expect($disk->get('notes/hello.txt'))->toBe('hello disk')
        ->and($disk->exists('notes/hello.txt'))->toBeTrue()
        ->and($disk->size('notes/hello.txt'))->toBe(10)
        ->and($disk->files('notes'))->toBe(['notes/hello.txt'])
        ->and($this->rustfs->objects($this->bucket)->exists('app/notes/hello.txt'))->toBeTrue()
        ->and(file_get_contents($disk->temporaryUrl('notes/hello.txt', now()->addMinute())))->toBe('hello disk');

    $disk->setTags('notes/hello.txt', ['kind' => 'note']);
    expect($disk->getTags('notes/hello.txt'))->toBe(['kind' => 'note']);

    $disk->delete('notes/hello.txt');
    expect($disk->exists('notes/hello.txt'))->toBeFalse();
});

it('delivers bucket events to a notification target', function () {
    // Changes server-wide settings (module switch + a temporary target), so it is opt-in.
    // The directory must exist on the RustFS host and be an absolute path.
    $queueDir = getenv('RUSTFS_LIVE_NOTIFY_QUEUE_DIR');
    if (! $queueDir) {
        $this->markTestSkipped('Set RUSTFS_LIVE_NOTIFY_QUEUE_DIR to run the notification test.');
    }

    $admin = $this->rustfs->admin();
    $target = 'pest' . strtolower(Str::random(6));
    $before = $admin->config()->moduleSwitches();

    try {
        $admin->config()->updateModuleSwitches(notify: true);
        $admin->notifications()->set('webhook', $target, [
            'endpoint' => 'https://example.com/rustfs-hook',
            'queue_dir' => $queueDir,
            'enable' => true,
        ]);

        $arn = collect($admin->notifications()->arns())->first(fn (string $arn) => str_contains($arn, ":{$target}:"));
        expect($arn)->toBe("arn:rustfs:sqs:us-east-1:{$target}:webhook");

        $this->rustfs->buckets()->create($this->bucket);
        $this->rustfs->buckets()->addNotification($this->bucket, $arn, ['s3:ObjectCreated:*'], suffix: '.jpg');
        expect($this->rustfs->buckets()->notifications($this->bucket)['QueueConfigurations'][0]['QueueArn'])->toBe($arn);

        $this->rustfs->buckets()->clearNotifications($this->bucket);
        $admin->notifications()->remove('webhook', $target);
    } finally {
        // del-config-kv works even when the notify runtime is unhappy
        rescue(fn () => $admin->config()->delete("notify_webhook:{$target}"), report: false);
        $admin->config()->updateModuleSwitches((bool) $before['notify_enabled'], (bool) $before['audit_enabled']);
    }
});
