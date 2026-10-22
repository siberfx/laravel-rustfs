<?php

declare(strict_types=1);

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Siberfx\RustFs\ConnectionConfig;
use Siberfx\RustFs\Exceptions\RustFsException;
use Siberfx\RustFs\RustFsConnection;

/**
 * S3 connection whose SDK handler records every serialized command and answers from $results
 * (array result or Closure(CommandInterface, RequestInterface): Result|Throwable, keyed by operation name).
 *
 * @param  array<string, Closure|array<string, mixed>>  $results
 */
function fakeS3(ArrayObject $calls, array $results = []): RustFsConnection
{
    $config = ConnectionConfig::fromArray([
        'endpoint' => 'http://rustfs.test:9000',
        'key' => 'rustfsadmin',
        'secret' => 'rustfssecret',
        'bucket' => 'media',
    ]);

    $handler = function (CommandInterface $command, RequestInterface $request) use ($calls, $results) {
        $calls[] = ['name' => $command->getName(), 'args' => $command->toArray(), 'request' => $request];
        $result = $results[$command->getName()] ?? [];
        if (is_array($result)) {
            // Waiters (BucketExists) look at the HTTP status in the metadata
            $result += ['@metadata' => ['statusCode' => 200]];
        }
        $result = $result instanceof Closure ? $result($command, $request) : new Result($result);

        return $result instanceof Throwable ? Create::rejectionFor($result) : Create::promiseFor($result);
    };

    return new RustFsConnection($config, null, new S3Client($config->s3ClientOptions() + ['handler' => $handler]));
}

function s3Error(CommandInterface $command, string $code, int $status = 404): S3Exception
{
    return new S3Exception($code, $command, ['code' => $code, 'response' => new Response($status)]);
}

beforeEach(function () {
    $this->calls = new ArrayObject;
});

it('builds a path-style S3 client from the connection config', function () {
    $options = ConnectionConfig::fromArray(['endpoint' => 'http://rustfs.test:9000/', 'key' => 'k', 'secret' => 's'])->s3ClientOptions();

    expect($options['endpoint'])->toBe('http://rustfs.test:9000')
        ->and($options['use_path_style_endpoint'])->toBeTrue()
        ->and($options['region'])->toBe('us-east-1')
        ->and($options['request_checksum_calculation'])->toBe('when_required');
});

it('creates object-lock buckets path-style and waits for them', function () {
    fakeS3($this->calls)->buckets()->create('vault', objectLock: true);

    [$create, $head] = $this->calls;
    expect($create['name'])->toBe('CreateBucket')
        ->and((string) $create['request']->getUri())->toBe('http://rustfs.test:9000/vault')
        ->and($create['request']->getHeaderLine('x-amz-bucket-object-lock-enabled'))->toBe('true')
        ->and($head['name'])->toBe('HeadBucket');
});

it('enables versioning after creation when asked', function () {
    fakeS3($this->calls)->buckets()->create('docs', versioning: true);

    expect(array_column($this->calls->getArrayCopy(), 'name'))->toBe(['CreateBucket', 'HeadBucket', 'PutBucketVersioning'])
        ->and($this->calls[2]['args']['VersioningConfiguration'])->toBe(['Status' => 'Enabled']);
});

it('lists buckets', function () {
    $buckets = fakeS3($this->calls, ['ListBuckets' => ['Buckets' => [['Name' => 'media', 'CreationDate' => null]]]])->buckets()->list();

    expect($buckets)->toBe([['name' => 'media', 'created' => null]]);
});

it('returns null or empty for unconfigured bucket sub-resources', function () {
    $connection = fakeS3($this->calls, [
        'GetBucketEncryption' => fn ($cmd) => s3Error($cmd, 'ServerSideEncryptionConfigurationNotFoundError'),
        'GetObjectLockConfiguration' => fn ($cmd) => s3Error($cmd, 'ObjectLockConfigurationNotFoundError'),
        'GetBucketPolicy' => fn ($cmd) => s3Error($cmd, 'NoSuchBucketPolicy'),
        'GetBucketLifecycleConfiguration' => fn ($cmd) => s3Error($cmd, 'NoSuchLifecycleConfiguration'),
        'GetBucketTagging' => fn ($cmd) => s3Error($cmd, 'NoSuchTagSet'),
        'GetBucketCors' => fn ($cmd) => s3Error($cmd, 'NoSuchCORSConfiguration'),
        'GetBucketReplication' => fn ($cmd) => s3Error($cmd, 'ReplicationConfigurationNotFoundError'),
    ]);
    $buckets = $connection->buckets();

    expect($buckets->encryption('media'))->toBeNull()
        ->and($buckets->objectLock('media'))->toBeNull()
        ->and($buckets->policy('media'))->toBeNull()
        ->and($buckets->lifecycle('media'))->toBe([])
        ->and($buckets->tags('media'))->toBe([])
        ->and($buckets->cors('media'))->toBe([])
        ->and($buckets->replication('media'))->toBeNull();
});

it('rethrows unexpected S3 errors', function () {
    fakeS3($this->calls, ['GetBucketPolicy' => fn ($cmd) => s3Error($cmd, 'AccessDenied', 403)])->buckets()->policy('media');
})->throws(S3Exception::class);

it('serializes bucket configuration calls', function () {
    $buckets = fakeS3($this->calls)->buckets();

    $buckets->putEncryption('media', 'aws:kms', 'app-key');
    $buckets->putObjectLock('media', 'COMPLIANCE', days: 30);
    $buckets->putTags('media', ['team' => 'web']);
    $buckets->makePublic('media', 'public/');
    $buckets->putLifecycle('media', [['ID' => 'tmp', 'Status' => 'Enabled', 'Filter' => ['Prefix' => 'tmp/'], 'Expiration' => ['Days' => 1]]]);

    [$encryption, $lock, $tags, $policy, $lifecycle] = $this->calls;
    expect($encryption['args']['ServerSideEncryptionConfiguration']['Rules'][0]['ApplyServerSideEncryptionByDefault'])
        ->toBe(['SSEAlgorithm' => 'aws:kms', 'KMSMasterKeyID' => 'app-key'])
        ->and($lock['args']['ObjectLockConfiguration'])->toBe(['ObjectLockEnabled' => 'Enabled', 'Rule' => ['DefaultRetention' => ['Mode' => 'COMPLIANCE', 'Days' => 30]]])
        ->and($tags['args']['Tagging']['TagSet'])->toBe([['Key' => 'team', 'Value' => 'web']])
        ->and(json_decode($policy['args']['Policy'], true)['Statement'][0]['Resource'])->toBe(['arn:aws:s3:::media/public/*'])
        ->and((string) $lifecycle['request']->getBody())->toContain('<Prefix>tmp/</Prefix>');
});

it('appends notification subscriptions to the existing configuration', function () {
    fakeS3($this->calls, ['GetBucketNotificationConfiguration' => ['QueueConfigurations' => [['QueueArn' => 'arn:old', 'Events' => ['s3:ObjectCreated:*']]]]])
        ->buckets()->addNotification('media', 'arn:new', ['s3:ObjectRemoved:*'], suffix: '.jpg');

    $queues = $this->calls[1]['args']['NotificationConfiguration']['QueueConfigurations'];
    expect($queues)->toHaveCount(2)
        ->and($queues[1])->toBe(['QueueArn' => 'arn:new', 'Events' => ['s3:ObjectRemoved:*'], 'Filter' => ['Key' => ['FilterRules' => [['Name' => 'suffix', 'Value' => '.jpg']]]]]);
});

it('empties a bucket including versions and delete markers', function () {
    $count = fakeS3($this->calls, [
        'ListObjectVersions' => [
            'Versions' => [['Key' => 'a', 'VersionId' => 'v1'], ['Key' => 'a', 'VersionId' => 'v2']],
            'DeleteMarkers' => [['Key' => 'b', 'VersionId' => 'm1']],
        ],
    ])->buckets()->delete('media', force: true);

    $names = array_column($this->calls->getArrayCopy(), 'name');
    expect($names)->toBe(['ListObjectVersions', 'DeleteObjects', 'DeleteBucket'])
        ->and($this->calls[1]['args']['Delete']['Objects'])->toBe([
            ['Key' => 'a', 'VersionId' => 'v1'],
            ['Key' => 'a', 'VersionId' => 'v2'],
            ['Key' => 'b', 'VersionId' => 'm1'],
        ]);
});

it('deletes many keys in batches of 1000', function () {
    $errors = fakeS3($this->calls, ['DeleteObjects' => fn ($cmd) => new Result(['Errors' => count($cmd['Delete']['Objects']) < 1000 ? [['Key' => 'x', 'Message' => 'locked']] : []])])
        ->objects()->deleteMany((function () {
            for ($i = 0; $i < 2500; $i++) {
                yield "k{$i}";
            }
        })());

    expect(array_map(fn ($c) => count($c['args']['Delete']['Objects']), $this->calls->getArrayCopy()))->toBe([1000, 1000, 500])
        ->and($errors)->toBe([['Key' => 'x', 'Message' => 'locked']]);
});

it('puts, reads and inspects objects', function () {
    $objects = fakeS3($this->calls, [
        'PutObject' => ['ETag' => '"abc"', 'VersionId' => 'v1'],
        'GetObject' => fn () => new Result(['Body' => Utils::streamFor('hello')]),
        'HeadObject' => fn ($cmd) => $cmd['Key'] === 'missing' ? s3Error($cmd, 'NotFound') : new Result(['ContentLength' => 5]),
    ])->objects();

    expect($objects->put('docs/a.txt', 'hello', ['ContentType' => 'text/plain', 'Metadata' => ['owner' => 'alice']]))->toMatchArray(['ETag' => '"abc"', 'VersionId' => 'v1'])
        ->and($objects->get('docs/a.txt', 'v1'))->toBe('hello')
        ->and($objects->head('docs/a.txt'))->toBe(['ContentLength' => 5])
        ->and($objects->exists('missing'))->toBeFalse();

    $put = $this->calls[0]['request'];
    expect((string) $put->getUri())->toBe('http://rustfs.test:9000/media/docs/a.txt')
        ->and($put->getHeaderLine('x-amz-meta-owner'))->toBe('alice')
        ->and($this->calls[1]['args']['VersionId'])->toBe('v1');
});

it('manages object tags, retention and legal hold', function () {
    $objects = fakeS3($this->calls, [
        'GetObjectTagging' => ['TagSet' => [['Key' => 'a', 'Value' => '1']]],
        'GetObjectLegalHold' => ['LegalHold' => ['Status' => 'ON']],
    ])->objects();

    $until = new DateTimeImmutable('2030-01-01T00:00:00Z');
    $objects->putTags('k', ['a' => '1']);
    $objects->putRetention('k', 'GOVERNANCE', $until, bypassGovernanceRetention: true);
    $objects->putLegalHold('k', true);

    expect($objects->tags('k'))->toBe(['a' => '1'])
        ->and($objects->legalHold('k'))->toBeTrue()
        ->and($this->calls[1]['args']['Retention'])->toBe(['Mode' => 'GOVERNANCE', 'RetainUntilDate' => $until])
        ->and($this->calls[1]['request']->getHeaderLine('x-amz-bypass-governance-retention'))->toBe('true')
        ->and($this->calls[2]['args']['LegalHold'])->toBe(['Status' => 'ON']);
});

it('copies objects with an encoded copy source', function () {
    fakeS3($this->calls)->objects()->copy('photos/a b.jpg', 'backup/a.jpg', 'inbox', 'v9');

    expect($this->calls[0]['args']['CopySource'])->toBe('inbox/photos/a%20b.jpg?versionId=v9');
});

it('lists objects lazily, with folders when not recursive', function () {
    $objects = fakeS3($this->calls, ['ListObjectsV2' => ['CommonPrefixes' => [['Prefix' => 'a/']], 'Contents' => [['Key' => 'b.txt', 'Size' => 3]]]])->objects();

    $entries = iterator_to_array($objects->list('', recursive: false), false);

    expect($entries)->toBe([['Prefix' => 'a/', 'IsDirectory' => true], ['Key' => 'b.txt', 'Size' => 3]])
        ->and($this->calls[0]['args']['Delimiter'])->toBe('/');
});

it('drives multipart uploads', function () {
    $objects = fakeS3($this->calls, [
        'CreateMultipartUpload' => ['UploadId' => 'up-1'],
        'UploadPart' => ['ETag' => '"p"'],
        'CompleteMultipartUpload' => ['ETag' => '"done-2"'],
    ])->objects();

    $id = $objects->createMultipartUpload('big.bin');
    $parts = [$objects->uploadPart('big.bin', $id, 2, 'bbb'), $objects->uploadPart('big.bin', $id, 1, 'aaa')];
    $result = $objects->completeMultipartUpload('big.bin', $id, $parts);

    expect($id)->toBe('up-1')
        ->and($result)->toMatchArray(['ETag' => '"done-2"'])
        ->and(array_column($this->calls[3]['args']['MultipartUpload']['Parts'], 'PartNumber'))->toBe([1, 2]);
});

it('presigns path-style GET and PUT URLs', function () {
    $objects = RustFsConnection::make(['endpoint' => 'http://rustfs.test:9000', 'key' => 'k', 'secret' => 's', 'bucket' => 'media'])->objects();

    $get = $objects->temporaryUrl('photos/cat 1.jpg', '+5 minutes', ['ResponseContentDisposition' => 'attachment']);
    $put = $objects->temporaryUploadUrl('uploads/a.bin', '+10 minutes');

    expect($get)->toStartWith('http://rustfs.test:9000/media/photos/cat%201.jpg?')
        ->toContain('X-Amz-Signature=')
        ->toContain('X-Amz-Expires=300')
        ->toContain('response-content-disposition=attachment')
        ->and($put)->toStartWith('http://rustfs.test:9000/media/uploads/a.bin?')
        ->toContain('X-Amz-Expires=600');
});

it('creates a presigned POST form', function () {
    $objects = RustFsConnection::make(['endpoint' => 'http://rustfs.test:9000', 'key' => 'k', 'secret' => 's', 'bucket' => 'media'])->objects();

    $form = $objects->presignedPost(['key' => 'uploads/${filename}'], [['starts-with', '$key', 'uploads/']]);

    expect($form['url'])->toBe('http://rustfs.test:9000/media')
        ->and($form['fields'])->toHaveKeys(['key', 'Policy', 'X-Amz-Signature', 'X-Amz-Credential']);
});

it('requires a bucket for object operations', function () {
    RustFsConnection::make(['endpoint' => 'http://rustfs.test:9000', 'key' => 'k', 'secret' => 's'])->objects();
})->throws(RustFsException::class, 'No bucket given');

it('targets another bucket on demand', function () {
    $connection = RustFsConnection::make(['endpoint' => 'http://rustfs.test:9000', 'key' => 'k', 'secret' => 's', 'bucket' => 'media']);

    expect($connection->objects('logs')->bucket)->toBe('logs')
        ->and($connection->objects()->bucket('other')->bucket)->toBe('other');
});
