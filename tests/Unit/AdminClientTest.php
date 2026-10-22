<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Siberfx\RustFs\Admin\AdminClient;
use Siberfx\RustFs\Admin\Enums\AccountStatus;
use Siberfx\RustFs\Exceptions\AdminApiException;
use Siberfx\RustFs\Tests\Fakes\FakeAdminHttp;

beforeEach(function () {
    $this->http = new FakeAdminHttp;
    $this->admin = $this->http->connection()->admin();
});

it('signs admin requests with SigV4 for the s3 service', function () {
    $this->admin->request('GET', '/v3/info');

    $request = $this->http->last();
    expect((string) $request->getUri())->toBe('http://rustfs.test:9000/rustfs/admin/v3/info')
        ->and($request->getHeaderLine('Authorization'))
        ->toStartWith('AWS4-HMAC-SHA256 Credential=rustfsadmin/')
        ->toContain('/us-east-1/s3/aws4_request')
        ->toContain('SignedHeaders=')
        ->and($request->getHeaderLine('X-Amz-Content-Sha256'))->toBe(hash('sha256', ''))
        ->and($request->getHeaderLine('X-Amz-Date'))->not->toBeEmpty();
});

it('hashes the JSON body into the signature', function () {
    $this->admin->request('PUT', '/v3/add-user', ['accessKey' => 'alice'], ['secretKey' => 's3cr3t']);

    $request = $this->http->last();
    expect((string) $request->getBody())->toBe('{"secretKey":"s3cr3t"}')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getHeaderLine('X-Amz-Content-Sha256'))->toBe(hash('sha256', '{"secretKey":"s3cr3t"}'));
});

it('sends the session token when configured', function () {
    $this->http->connection(['token' => 'sts-token'])->admin()->request('GET', '/v3/info');

    expect($this->http->last()->getHeaderLine('X-Amz-Security-Token'))->toBe('sts-token');
});

it('honours a custom admin prefix', function () {
    $this->http->connection(['admin_prefix' => 'minio/admin/'])->admin()->request('GET', '/v3/info');

    expect($this->http->last()->getUri()->getPath())->toBe('/minio/admin/v3/info');
});

it('builds RustFS style query strings', function () {
    expect(AdminClient::buildQuery([
        'user' => ['a', 'b c'],
        'isGroup' => false,
        'force' => true,
        'skip' => null,
        'status' => AccountStatus::Disabled,
    ]))->toBe('user=a&user=b%20c&isGroup=false&force=true&status=disabled');
});

it('percent-encodes path segments but keeps separators', function () {
    expect(AdminClient::encodePath('/v3/heal/my bucket/a+b/c'))->toBe('/v3/heal/my%20bucket/a%2Bb/c');
});

it('encodes an empty array as a JSON object', function () {
    expect(AdminClient::encodeJson([]))->toBe('{}');
});

it('decodes JSON, text and empty bodies', function () {
    $this->http->queue(
        new Response(200, ['Content-Type' => 'application/json'], '{"a":1}'),
        new Response(200, [], '[1,2]'),
        new Response(200, ['Content-Type' => 'text/plain'], 'api requests_max=0'),
        new Response(200, [], ''),
    );

    expect($this->admin->request('GET', '/x'))->toBe(['a' => 1])
        ->and($this->admin->request('GET', '/x'))->toBe([1, 2])
        ->and($this->admin->request('GET', '/x'))->toBe('api requests_max=0')
        ->and($this->admin->request('GET', '/x'))->toBeNull();
});

it('turns S3 XML errors into AdminApiException', function () {
    $this->http->queue(new Response(403, ['x-amz-request-id' => 'REQ1'], '<?xml version="1.0"?><Error><Code>AccessDenied</Code><Message>Access Denied.</Message></Error>'));

    try {
        $this->admin->users()->list();
        $this->fail('Expected exception');
    } catch (AdminApiException $e) {
        expect($e->status)->toBe(403)
            ->and($e->errorCode)->toBe('AccessDenied')
            ->and($e->requestId)->toBe('REQ1')
            ->and($e->getMessage())->toContain('Access Denied.')->toContain('/v3/list-users');
    }
});

it('retries 503 responses and re-signs each attempt', function () {
    $admin = $this->http->connection(['admin_retry_delay_ms' => 0])->admin();
    $this->http->queue(new Response(503), new Response(503), new Response(200, [], '{"quota":1}'));

    expect($admin->quotas()->get('media'))->toBe(['quota' => 1])
        ->and($this->http->history)->toHaveCount(3);
});

it('gives up after the configured retries', function () {
    $admin = $this->http->connection(['admin_retries' => 1, 'admin_retry_delay_ms' => 0])->admin();
    $this->http->queue(new Response(503), new Response(503), new Response(200));

    expect(fn () => $admin->system()->info())->toThrow(AdminApiException::class)
        ->and($this->http->history)->toHaveCount(2);
});

it('turns JSON errors into AdminApiException', function () {
    $this->http->queue(new Response(400, ['Content-Type' => 'application/json'], '{"Code":"InvalidArgument","Message":"secret key is required"}'));

    expect(fn () => $this->admin->users()->create('bob', ''))
        ->toThrow(AdminApiException::class, 'secret key is required');
});
