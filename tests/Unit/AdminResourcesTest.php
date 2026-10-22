<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Siberfx\RustFs\Admin\Admin;
use Siberfx\RustFs\Admin\Enums\AccountStatus;
use Siberfx\RustFs\Admin\Enums\SpeedtestType;
use Siberfx\RustFs\Tests\Fakes\FakeAdminHttp;

beforeEach(function () {
    $this->http = new FakeAdminHttp;
    $this->admin = $this->http->connection()->admin();
});

/**
 * Every row: [call, method, path below /rustfs/admin, expected query, expected JSON body (null = not checked)].
 */
dataset('routes', [
    // users
    'users.list' => [fn (Admin $a) => $a->users()->list(), 'GET', '/v3/list-users', [], null],
    'users.list bucket' => [fn (Admin $a) => $a->users()->list('media'), 'GET', '/v3/list-users', ['bucket' => ['media']], null],
    'users.info' => [fn (Admin $a) => $a->users()->info('alice'), 'GET', '/v3/user-info', ['accessKey' => ['alice']], null],
    'users.create' => [fn (Admin $a) => $a->users()->create('alice', 'secret123'), 'PUT', '/v3/add-user', ['accessKey' => ['alice']], ['secretKey' => 'secret123', 'status' => 'enabled']],
    'users.delete' => [fn (Admin $a) => $a->users()->delete('alice'), 'DELETE', '/v3/remove-user', ['accessKey' => ['alice']], null],
    'users.disable' => [fn (Admin $a) => $a->users()->disable('alice'), 'PUT', '/v3/set-user-status', ['accessKey' => ['alice'], 'status' => ['disabled']], null],
    'users.setSecretKey' => [fn (Admin $a) => $a->users()->setSecretKey('alice', 'n3w'), 'PUT', '/v3/set-user-secret-key', ['accessKey' => ['alice']], ['secret_key' => 'n3w']],
    'users.setPolicy' => [fn (Admin $a) => $a->users()->setPolicy('alice', 'readonly'), 'PUT', '/v3/set-user-or-group-policy', ['policyName' => ['readonly'], 'userOrGroup' => ['alice'], 'isGroup' => ['false']], null],
    'users.mfa' => [fn (Admin $a) => $a->users()->resetMfa('alice'), 'DELETE', '/v3/user/mfa', ['accessKey' => ['alice']], null],

    // groups
    'groups.list' => [fn (Admin $a) => $a->groups()->list(), 'GET', '/v3/groups', [], null],
    'groups.info' => [fn (Admin $a) => $a->groups()->info('devs'), 'GET', '/v3/group', ['group' => ['devs']], null],
    'groups.add' => [fn (Admin $a) => $a->groups()->addMembers('devs', ['alice', 'bob']), 'PUT', '/v3/update-group-members', [], ['group' => 'devs', 'members' => ['alice', 'bob'], 'groupStatus' => 'enabled', 'isRemove' => false]],
    'groups.remove' => [fn (Admin $a) => $a->groups()->removeMembers('devs', ['bob']), 'PUT', '/v3/update-group-members', [], ['group' => 'devs', 'members' => ['bob'], 'groupStatus' => 'enabled', 'isRemove' => true]],
    'groups.delete' => [fn (Admin $a) => $a->groups()->delete('devs'), 'DELETE', '/v3/group/devs', [], null],
    'groups.status' => [fn (Admin $a) => $a->groups()->setStatus('devs', AccountStatus::Disabled), 'PUT', '/v3/set-group-status', ['group' => ['devs'], 'status' => ['disabled']], null],
    'groups.policy' => [fn (Admin $a) => $a->groups()->setPolicy('devs', 'readwrite'), 'PUT', '/v3/set-user-or-group-policy', ['policyName' => ['readwrite'], 'userOrGroup' => ['devs'], 'isGroup' => ['true']], null],

    // policies
    'policies.list' => [fn (Admin $a) => $a->policies()->list(), 'GET', '/v3/list-canned-policies', [], null],
    'policies.info' => [fn (Admin $a) => $a->policies()->info('readonly'), 'GET', '/v3/info-canned-policy', ['name' => ['readonly']], null],
    'policies.create' => [fn (Admin $a) => $a->policies()->create('media-ro', ['Version' => '2012-10-17', 'Statement' => []]), 'PUT', '/v3/add-canned-policy', ['name' => ['media-ro']], ['Version' => '2012-10-17', 'Statement' => []]],
    'policies.delete' => [fn (Admin $a) => $a->policies()->delete('media-ro'), 'DELETE', '/v3/remove-canned-policy', ['name' => ['media-ro']], null],
    'policies.attach' => [fn (Admin $a) => $a->policies()->attach(['readonly'], user: 'alice'), 'POST', '/v3/idp/builtin/policy/attach', [], ['policies' => ['readonly'], 'user' => 'alice']],
    'policies.detach' => [fn (Admin $a) => $a->policies()->detach(['readonly'], group: 'devs'), 'POST', '/v3/idp/builtin/policy/detach', [], ['policies' => ['readonly'], 'group' => 'devs']],
    'policies.entities' => [fn (Admin $a) => $a->policies()->entities(['alice', 'bob'], [], ['readonly']), 'GET', '/v3/idp/builtin/policy-entities', ['user' => ['alice', 'bob'], 'policy' => ['readonly']], null],

    // service accounts
    'sa.create' => [fn (Admin $a) => $a->serviceAccounts()->create(targetUser: 'alice', name: 'ci'), 'PUT', '/v3/add-service-account', [], ['targetUser' => 'alice', 'accessKey' => '', 'secretKey' => '', 'name' => 'ci']],
    'sa.update' => [fn (Admin $a) => $a->serviceAccounts()->disable('AKIA1'), 'POST', '/v3/update-service-account', ['accessKey' => ['AKIA1']], ['newStatus' => 'off']],
    'sa.list' => [fn (Admin $a) => $a->serviceAccounts()->list('alice'), 'GET', '/v3/list-service-accounts', ['user' => ['alice']], null],
    'sa.info' => [fn (Admin $a) => $a->serviceAccounts()->info('AKIA1'), 'GET', '/v3/info-service-account', ['accessKey' => ['AKIA1']], null],
    'sa.delete' => [fn (Admin $a) => $a->serviceAccounts()->delete('AKIA1'), 'DELETE', '/v3/delete-service-account', ['accessKey' => ['AKIA1']], null],
    'sa.bulk' => [fn (Admin $a) => $a->serviceAccounts()->listBulk(['alice', 'bob']), 'GET', '/v3/list-access-keys-bulk', ['users' => ['alice,bob'], 'all' => ['false'], 'listType' => ['all']], null],

    // account
    'account.info' => [fn (Admin $a) => $a->account()->info(), 'GET', '/v3/accountinfo', [], null],
    'account.password' => [fn (Admin $a) => $a->account()->changePassword('old', 'new'), 'POST', '/v3/account/password', [], ['current_secret_key' => 'old', 'new_secret_key' => 'new']],
    'account.isAdmin' => [fn (Admin $a) => $a->account()->isAdmin(), 'GET', '/v3/is-admin', [], null],

    // system
    'system.info' => [fn (Admin $a) => $a->system()->info(), 'GET', '/v3/info', [], null],
    'system.storage' => [fn (Admin $a) => $a->system()->storageInfo(), 'GET', '/v3/storageinfo', [], null],
    'config.switches' => [fn (Admin $a) => $a->config()->updateModuleSwitches(true, false), 'PUT', '/v3/module-switches', [], ['notify_enabled' => true, 'audit_enabled' => false]],
    'system.usage' => [fn (Admin $a) => $a->system()->dataUsageInfo(), 'GET', '/v3/datausageinfo', [], null],
    'system.prefixUsage' => [fn (Admin $a) => $a->system()->prefixUsage('media'), 'GET', '/v3/usage/media', [], null],
    'system.restart' => [fn (Admin $a) => $a->system()->restart(), 'POST', '/v3/service', ['action' => ['restart']], null],
    'system.capabilities' => [fn (Admin $a) => $a->system()->capabilities(), 'GET', '/v4/runtime/capabilities', [], null],

    // quotas
    'quota.set' => [fn (Admin $a) => $a->quotas()->set('media', 1073741824), 'PUT', '/v3/quota/media', [], ['quota' => 1073741824, 'quota_type' => 'hard']],
    'quota.get' => [fn (Admin $a) => $a->quotas()->get('media'), 'GET', '/v3/quota/media', [], null],
    'quota.clear' => [fn (Admin $a) => $a->quotas()->clear('media'), 'DELETE', '/v3/quota/media', [], null],
    'quota.stats' => [fn (Admin $a) => $a->quotas()->stats('media'), 'GET', '/v3/quota-stats/media', [], null],
    'quota.check' => [fn (Admin $a) => $a->quotas()->check('media', 2048), 'POST', '/v3/quota-check/media', [], ['operation_type' => 'PUT', 'operation_size' => 2048]],

    // tiers
    'tiers.list' => [fn (Admin $a) => $a->tiers()->list(), 'GET', '/v3/tier', [], null],
    'tiers.add' => [fn (Admin $a) => $a->tiers()->add(['Type' => 's3', 'Name' => 'COLD']), 'PUT', '/v3/tier', [], ['Type' => 's3', 'Name' => 'COLD']],
    'tiers.edit' => [fn (Admin $a) => $a->tiers()->edit('COLD', ['access' => 'a', 'secret' => 'b']), 'POST', '/v3/tier/COLD', [], ['access' => 'a', 'secret' => 'b']],
    'tiers.remove' => [fn (Admin $a) => $a->tiers()->remove('COLD', true), 'DELETE', '/v3/tier/COLD', ['force' => ['true']], null],
    'tiers.verify' => [fn (Admin $a) => $a->tiers()->verify('COLD'), 'GET', '/v3/tier/COLD', [], null],
    'tiers.stats' => [fn (Admin $a) => $a->tiers()->stats(), 'GET', '/v3/tier-stats', [], null],

    // pools / rebalance
    'pools.list' => [fn (Admin $a) => $a->pools()->list(), 'GET', '/v3/pools/list', [], null],
    'pools.status' => [fn (Admin $a) => $a->pools()->status('0', true), 'GET', '/v3/pools/status', ['pool' => ['0'], 'by-id' => ['true']], null],
    'pools.decommission' => [fn (Admin $a) => $a->pools()->decommission('http://s{1...4}/d{1...4}'), 'POST', '/v3/pools/decommission', ['pool' => ['http://s{1...4}/d{1...4}'], 'by-id' => ['false']], null],
    'pools.cancel' => [fn (Admin $a) => $a->pools()->cancelDecommission('0', true), 'POST', '/v3/pools/cancel', ['pool' => ['0'], 'by-id' => ['true']], null],
    'rebalance.start' => [fn (Admin $a) => $a->pools()->startRebalance(), 'POST', '/v3/rebalance/start', [], null],
    'rebalance.status' => [fn (Admin $a) => $a->pools()->rebalanceStatus(), 'GET', '/v3/rebalance/status', [], null],
    'rebalance.stop' => [fn (Admin $a) => $a->pools()->stopRebalance(), 'POST', '/v3/rebalance/stop', [], null],

    // heal
    'heal.all' => [fn (Admin $a) => $a->heal()->start(), 'POST', '/v3/heal/', [], []],
    'heal.prefix' => [fn (Admin $a) => $a->heal()->start('media', 'photos/2026', ['recursive' => true]), 'POST', '/v3/heal/media/photos/2026', [], ['recursive' => true]],
    'heal.status' => [fn (Admin $a) => $a->heal()->status('tok-1', 'media'), 'POST', '/v3/heal/media', ['clientToken' => ['tok-1']], null],
    'heal.stop' => [fn (Admin $a) => $a->heal()->stop('media'), 'POST', '/v3/heal/media', ['forceStop' => ['true']], null],
    'heal.background' => [fn (Admin $a) => $a->heal()->backgroundStatus(), 'POST', '/v3/background-heal/status', [], null],

    // kms
    'kms.status' => [fn (Admin $a) => $a->kms()->status(), 'GET', '/v3/kms/status', [], null],
    'kms.createKey' => [fn (Admin $a) => $a->kms()->createKey('app key', ['env' => 'prod']), 'POST', '/v3/kms/keys', [], ['description' => 'app key', 'tags' => ['env' => 'prod']]],
    'kms.listKeys' => [fn (Admin $a) => $a->kms()->listKeys(['limit' => 10]), 'GET', '/v3/kms/keys', ['limit' => ['10']], null],
    'kms.describe' => [fn (Admin $a) => $a->kms()->describeKey('k1'), 'GET', '/v3/kms/keys/k1', [], null],
    'kms.delete' => [fn (Admin $a) => $a->kms()->deleteKey('k1', 7), 'DELETE', '/v3/kms/keys/delete', [], ['key_id' => 'k1', 'pending_window_in_days' => 7]],
    'kms.rotate' => [fn (Admin $a) => $a->kms()->rotateKey('k1'), 'POST', '/v3/kms/keys/rotate', [], ['key_id' => 'k1']],
    'kms.untag' => [fn (Admin $a) => $a->kms()->untagKey('k1', ['env']), 'POST', '/v3/kms/keys/untag', [], ['key_id' => 'k1', 'tag_keys' => ['env']]],
    'kms.dataKey' => [fn (Admin $a) => $a->kms()->generateDataKey('k1'), 'POST', '/v3/kms/generate-data-key', [], ['key_id' => 'k1', 'key_spec' => 'Aes256']],
    'kms.restoreDry' => [fn (Admin $a) => $a->kms()->restore(['snapshot' => 'x'], dryRun: true), 'POST', '/v3/kms/restore/dry-run', [], ['snapshot' => 'x']],

    // replication
    'repl.targets' => [fn (Admin $a) => $a->replication()->remoteTargets('media'), 'GET', '/v3/list-remote-targets', ['bucket' => ['media']], null],
    'repl.set' => [fn (Admin $a) => $a->replication()->setRemoteTarget('media', ['endpoint' => 'b:9000']), 'PUT', '/v3/set-remote-target', ['bucket' => ['media']], ['endpoint' => 'b:9000']],
    'repl.remove' => [fn (Admin $a) => $a->replication()->removeRemoteTarget('media', 'arn:x'), 'DELETE', '/v3/remove-remote-target', ['bucket' => ['media'], 'arn' => ['arn:x']], null],
    'repl.metrics' => [fn (Admin $a) => $a->replication()->metrics('media'), 'GET', '/v3/replicationmetrics', ['bucket' => ['media']], null],

    // site replication
    'sr.add' => [fn (Admin $a) => $a->siteReplication()->add([['name' => 'a'], ['name' => 'b']]), 'PUT', '/v3/site-replication/add', [], [['name' => 'a'], ['name' => 'b']]],
    'sr.info' => [fn (Admin $a) => $a->siteReplication()->info(), 'GET', '/v3/site-replication/info', [], null],
    'sr.resync' => [fn (Admin $a) => $a->siteReplication()->resync('start', ['name' => 'b']), 'PUT', '/v3/site-replication/resync/op', ['operation' => ['start']], ['name' => 'b']],

    // config
    'config.get' => [fn (Admin $a) => $a->config()->get('api'), 'GET', '/v3/get-config-kv', ['key' => ['api']], null],
    'config.history' => [fn (Admin $a) => $a->config()->history(5), 'GET', '/v3/list-config-history-kv', ['count' => ['5']], null],
    'config.restore' => [fn (Admin $a) => $a->config()->restore('r1'), 'PUT', '/v3/restore-config-history-kv', ['restoreId' => ['r1']], null],
    'config.modules' => [fn (Admin $a) => $a->config()->moduleSwitches(), 'GET', '/v3/module-switches', [], null],

    // notification / audit targets
    'notify.list' => [fn (Admin $a) => $a->notifications()->list(), 'GET', '/v3/target/list', [], null],
    'notify.set' => [fn (Admin $a) => $a->notifications()->set('webhook', 'primary', ['endpoint' => 'https://hook', 'enable' => true]), 'PUT', '/v3/target/notify_webhook/primary', [], ['key_values' => [['key' => 'endpoint', 'value' => 'https://hook'], ['key' => 'enable', 'value' => 'on']]]],
    'notify.remove' => [fn (Admin $a) => $a->notifications()->remove('webhook', 'primary'), 'DELETE', '/v3/target/notify_webhook/primary/reset', [], null],
    'notify.full name' => [fn (Admin $a) => $a->notifications()->remove('notify_kafka', 'k1'), 'DELETE', '/v3/target/notify_kafka/k1/reset', [], null],
    'notify.subs' => [fn (Admin $a) => $a->notifications()->subscriptions('mqtt', 'm1'), 'GET', '/v3/target/notify_mqtt/m1/subscriptions', [], null],
    'notify.arns' => [fn (Admin $a) => $a->notifications()->arns(), 'GET', '/v3/target/arns', [], null],
    'audit.set' => [fn (Admin $a) => $a->audit()->set('webhook', 'siem', ['endpoint' => 'https://siem']), 'PUT', '/v3/audit/target/audit_webhook/siem', [], ['key_values' => [['key' => 'endpoint', 'value' => 'https://siem']]]],
    'audit.list' => [fn (Admin $a) => $a->audit()->list(), 'GET', '/v3/audit/target/list', [], null],

    // bucket admin
    'bucket.durability' => [fn (Admin $a) => $a->buckets()->setDurability('media', 'strict'), 'PUT', '/v3/bucket-durability/media', [], ['mode' => 'strict']],
    'bucket.migration' => [fn (Admin $a) => $a->buckets()->migrationStatus('media'), 'GET', '/v3/on-demand-migration/media/status', [], null],
    'bucket.zip' => [fn (Admin $a) => $a->buckets()->createZipDownload(['bucket' => 'media', 'objects' => ['a.jpg']]), 'POST', '/v3/object-zip-downloads', [], ['bucket' => 'media', 'objects' => ['a.jpg']]],
    'bucket.zip id' => [fn (Admin $a) => $a->buckets()->downloadZip('abc123'), 'GET', '/v3/object-zip-downloads/abc123.zip', [], null],
    'bucket.zip url' => [fn (Admin $a) => $a->buckets()->downloadZip('http://rustfs.test:9000/rustfs/admin/v3/object-zip-downloads/abc123.zip?x=1'), 'GET', '/v3/object-zip-downloads/abc123.zip', [], null],
    'bucket.recover' => [fn (Admin $a) => $a->buckets()->recoverOrphaned('media'), 'POST', '/v3/recover-orphaned-bucket/media', [], []],

    // ilm, diagnostics, profiling, extensions
    'ilm.run' => [fn (Admin $a) => $a->ilm()->runTransition(['bucket' => 'logs', 'tier' => 'COLD']), 'POST', '/v3/ilm/transition/run', [], ['bucket' => 'logs', 'tier' => 'COLD']],
    'ilm.job' => [fn (Admin $a) => $a->ilm()->cancelTransitionJob('j1'), 'DELETE', '/v3/ilm/transition/jobs/j1', [], null],
    'diag.locks' => [fn (Admin $a) => $a->diagnostics()->topLocks(['count' => 5]), 'GET', '/v3/top/locks', ['count' => ['5']], null],
    'diag.speedtest' => [fn (Admin $a) => $a->diagnostics()->speedtest(SpeedtestType::Drive), 'POST', '/v3/speedtest/drive', [], null],
    'diag.scanner' => [fn (Admin $a) => $a->diagnostics()->scannerStatus(), 'GET', '/v3/scanner/status', [], null],
    'profiling.start' => [fn (Admin $a) => $a->profiling()->start('cpu,mem'), 'POST', '/v3/profiling/start', ['profilerType' => ['cpu,mem']], null],
    'ext.plugins' => [fn (Admin $a) => $a->extensions()->plugins(), 'GET', '/v4/plugins/instances', [], null],
]);

it('maps resource calls onto the RustFS admin routes', function (Closure $call, string $method, string $path, array $query, ?array $body) {
    $call($this->admin);

    $request = $this->http->last();
    expect($request->getMethod())->toBe($method)
        ->and(rawurldecode($request->getUri()->getPath()))->toBe('/rustfs/admin' . $path)
        ->and($request->getHeaderLine('Authorization'))->toStartWith('AWS4-HMAC-SHA256');

    $sent = $this->http->lastQuery();
    foreach ($query as $key => $values) {
        expect($sent[$key] ?? null)->toBe($values, "query parameter [{$key}]");
    }

    if ($body !== null) {
        expect($this->http->lastJson())->toBe($body);
    }
})->with('routes');

it('attaches the policy of a new user with a second call', function () {
    $this->admin->users()->create('alice', 'secret123', 'readwrite');

    [$add, $policy] = array_column($this->http->history, 'request');
    expect($add->getUri()->getPath())->toBe('/rustfs/admin/v3/add-user')
        ->and(json_decode((string) $add->getBody(), true))->toBe(['secretKey' => 'secret123', 'status' => 'enabled'])
        ->and($policy->getMethod())->toBe('PUT')
        ->and($policy->getUri()->getPath())->toBe('/rustfs/admin/v3/set-user-or-group-policy')
        ->and($this->http->lastQuery())->toBe(['policyName' => ['readwrite'], 'userOrGroup' => ['alice'], 'isGroup' => ['false']]);
});

it('sends raw strings for config KV and archives', function () {
    $this->http->queue(new Response(200, ['x-rustfs-config-applied' => 'true'], ''));

    expect($this->admin->config()->set('api requests_max=100'))->toBeTrue()
        ->and((string) $this->http->last()->getBody())->toBe('api requests_max=100')
        ->and($this->http->last()->getHeaderLine('Content-Type'))->toBe('application/octet-stream');

    $this->admin->iam()->import('PK-zip-bytes');
    expect($this->http->last()->getMethod())->toBe('PUT')
        ->and($this->http->last()->getUri()->getPath())->toBe('/rustfs/admin/v3/import-iam')
        ->and((string) $this->http->last()->getBody())->toBe('PK-zip-bytes');
});

it('returns binary exports untouched', function () {
    $this->http->queue(new Response(200, ['Content-Type' => 'application/zip'], "PK\x03\x04data"));

    expect($this->admin->buckets()->exportMetadata('media'))->toBe("PK\x03\x04data")
        ->and($this->http->lastQuery())->toBe(['bucket' => ['media']]);
});

it('unwraps service account credentials and lists', function () {
    $this->http->queue(
        new Response(200, [], '{"credentials":{"accessKey":"AK","secretKey":"SK","expiration":null}}'),
        new Response(200, [], '{"accounts":[{"accessKey":"AK","parentUser":"alice"}]}'),
    );

    expect($this->admin->serviceAccounts()->create())->toBe(['accessKey' => 'AK', 'secretKey' => 'SK', 'expiration' => null])
        ->and($this->admin->serviceAccounts()->list())->toBe([['accessKey' => 'AK', 'parentUser' => 'alice']]);
});

it('streams trace events line by line', function () {
    $this->http->queue(new Response(200, ['Content-Type' => 'application/x-ndjson'], "{\"type\":1,\"path\":\"/a\"}\n\n{\"type\":1,\"path\":\"/b\"}\n{\"type\":1,\"path\":\"/c\"}\n"));

    $events = iterator_to_array($this->admin->profiling()->traceEvents(['s3' => true], limit: 2), false);

    expect(array_column($events, 'path'))->toBe(['/a', '/b'])
        ->and($this->http->lastQuery())->toBe(['s3' => ['true']]);
});

it('keeps the current value of a module switch that is not given', function () {
    $this->http->queue(new Response(200, [], '{"notify_enabled":false,"audit_enabled":true}'));

    $this->admin->config()->updateModuleSwitches(notify: true);

    expect($this->http->history)->toHaveCount(2)
        ->and($this->http->lastJson())->toBe(['notify_enabled' => true, 'audit_enabled' => true]);
});

it('unwraps the storage info envelope', function () {
    $this->http->queue(new Response(200, [], '{"info":{"disks":[]},"admin_discovery":{}}'));

    expect($this->admin->system()->storageInfo())->toBe(['disks' => []]);
});

it('unwraps the server info envelope', function () {
    $this->http->queue(new Response(200, [], '{"info":{"mode":"online"},"admin_discovery":{}}'));

    expect($this->admin->system()->info())->toBe(['mode' => 'online']);
});

it('reaches unwrapped routes through request()', function () {
    $this->admin->request('GET', '/v4/heal/replacement-recovery');

    expect($this->http->last()->getUri()->getPath())->toBe('/rustfs/admin/v4/heal/replacement-recovery');
});
