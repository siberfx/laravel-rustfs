<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Siberfx\RustFs\Admin\Admin;
use Siberfx\RustFs\Exceptions\RustFsException;
use Siberfx\RustFs\Facades\RustFs;
use Siberfx\RustFs\Filesystem\RustFsFilesystemAdapter;
use Siberfx\RustFs\RustFsConnection;
use Siberfx\RustFs\RustFsManager;
use Siberfx\RustFs\Tests\Fakes\FakeAdminHttp;

it('registers the manager, facade and default bindings', function () {
    expect(app(RustFsManager::class))->toBe(app('rustfs'))
        ->and(RustFs::connection())->toBeInstanceOf(RustFsConnection::class)
        ->and(RustFs::connection())->toBe(RustFs::connection('default'))
        ->and(app(RustFsConnection::class)->config()->endpoint)->toBe('http://rustfs.test:9000')
        ->and(app(Admin::class))->toBeInstanceOf(Admin::class);
});

it('resolves named connections', function () {
    $backup = RustFs::connection('backup')->config();

    expect($backup->endpoint)->toBe('https://backup.example.com')
        ->and($backup->region)->toBe('eu-central-1')
        ->and($backup->bucket)->toBe('archive')
        ->and(RustFs::objects()->bucket)->toBe('media');
});

it('fails clearly for unknown connections', function () {
    RustFs::connection('nope');
})->throws(RustFsException::class, 'RustFS connection [nope] is not configured.');

it('provides a rustfs filesystem disk backed by the connection', function () {
    $disk = Storage::disk('rustfs');

    expect($disk)->toBeInstanceOf(RustFsFilesystemAdapter::class)
        ->and($disk->bucketName())->toBe('media')
        ->and($disk->url('a/b.jpg'))->toBe('http://rustfs.test:9000/media/a/b.jpg')
        ->and($disk->getClient()->getEndpoint()->getHost())->toBe('rustfs.test')
        ->and($disk->getClient()->getRegion())->toBe('us-east-1');

    $url = $disk->temporaryUrl('a/b.jpg', now()->addMinutes(5));
    expect($url)->toStartWith('http://rustfs.test:9000/media/a/b.jpg?')->toContain('X-Amz-Signature=');

    ['url' => $uploadUrl] = $disk->temporaryUploadUrl('incoming/c.bin', now()->addMinutes(5));
    expect($uploadUrl)->toStartWith('http://rustfs.test:9000/media/incoming/c.bin?');
});

it('lets disks override the connection, bucket and root', function () {
    $disk = Storage::disk('backups');

    expect($disk->bucketName())->toBe('dumps')
        ->and($disk->key('db.sql.gz'))->toBe('nightly/db.sql.gz')
        ->and($disk->url('db.sql.gz'))->toBe('https://backup.example.com/dumps/nightly/db.sql.gz')
        ->and($disk->getClient()->getRegion())->toBe('eu-central-1')
        ->and($disk->objects()->bucket)->toBe('dumps');
});

it('builds disks on the fly', function () {
    $disk = Storage::build(['driver' => 'rustfs', 'bucket' => 'adhoc', 'url' => 'https://cdn.example.com']);

    expect($disk->url('x.png'))->toBe('https://cdn.example.com/x.png');
});

it('requires a bucket for the disk', function () {
    config()->set('rustfs.connections.default.bucket', null);
    Storage::build(['driver' => 'rustfs']);
})->throws(RustFsException::class, 'needs a "bucket"');

it('registers the artisan commands', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('rustfs:health', 'rustfs:info', 'rustfs:bucket', 'rustfs:user');
});

it('runs rustfs:user list against the admin API', function () {
    $http = new FakeAdminHttp;
    $http->queue(new Response(200, [], '{"alice":{"status":"enabled","policyName":"readwrite","memberOf":["devs"]}}'));
    app(RustFsManager::class)->setConnection('default', $http->connection());

    $this->artisan('rustfs:user')
        ->expectsTable(['Access key', 'Status', 'Policy', 'Groups'], [['alice', 'enabled', 'readwrite', 'devs']])
        ->assertSuccessful();

    expect($http->last()->getUri()->getPath())->toBe('/rustfs/admin/v3/list-users');
});

it('reports failing probes from rustfs:health', function () {
    $http = new FakeAdminHttp;
    $http->queue(new Response(200), new Response(503), new Response(200), new Response(200));
    app(RustFsManager::class)->setConnection('default', $http->connection());

    $this->artisan('rustfs:health')->assertFailed();

    expect(array_map(fn ($h) => $h['request']->getUri()->getPath(), $http->history))
        ->toBe(['/health/live', '/health/ready', '/minio/health/cluster', '/minio/health/cluster/read']);
});

it('sets a bucket quota through rustfs:bucket', function () {
    $http = new FakeAdminHttp;
    app(RustFsManager::class)->setConnection('default', $http->connection());

    $this->artisan('rustfs:bucket', ['action' => 'quota', 'bucket' => 'media', 'value' => '1024'])->assertSuccessful();

    [$set, $get] = $http->history;
    expect($set['request']->getMethod())->toBe('PUT')
        ->and(json_decode((string) $set['request']->getBody(), true))->toBe(['quota' => 1024, 'quota_type' => 'hard'])
        ->and($get['request']->getUri()->getPath())->toBe('/rustfs/admin/v3/quota/media');
});
