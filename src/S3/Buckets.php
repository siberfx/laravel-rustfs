<?php

declare(strict_types=1);

namespace Siberfx\RustFs\S3;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use DateTimeInterface;
use Siberfx\RustFs\Exceptions\RustFsException;

/**
 * Bucket level S3 operations: lifecycle of buckets and all bucket sub-resources
 * (versioning, object lock, lifecycle, replication, encryption, policy, tagging, CORS, notifications).
 */
class Buckets
{
    public function __construct(private readonly S3Client $s3) {}

    /**
     * @return list<array{name: string, created: DateTimeInterface|null}>
     */
    public function list(): array
    {
        return array_map(
            static fn (array $bucket) => ['name' => (string) $bucket['Name'], 'created' => $bucket['CreationDate'] ?? null],
            $this->s3->listBuckets()['Buckets'] ?? [],
        );
    }

    public function exists(string $bucket): bool
    {
        return $this->s3->doesBucketExistV2($bucket, false);
    }

    /**
     * @param  bool  $objectLock  enable object lock (WORM); implies versioning and cannot be undone
     */
    public function create(string $bucket, bool $objectLock = false, bool $versioning = false): void
    {
        $args = ['Bucket' => $bucket];
        if ($objectLock) {
            $args['ObjectLockEnabledForBucket'] = true;
        }

        $this->s3->createBucket($args);
        $this->s3->waitUntil('BucketExists', ['Bucket' => $bucket]);

        if ($versioning && ! $objectLock) {
            $this->enableVersioning($bucket);
        }
    }

    /**
     * @param  bool  $force  remove every object version and delete marker first
     */
    public function delete(string $bucket, bool $force = false): void
    {
        if ($force) {
            $this->empty($bucket);
        }

        $this->s3->deleteBucket(['Bucket' => $bucket]);
    }

    /**
     * Delete every object, version and delete marker in the bucket.
     *
     * @return int number of deleted entries
     */
    public function empty(string $bucket, bool $bypassGovernanceRetention = false): int
    {
        $entries = [];
        foreach ((new Objects($this->s3, $bucket))->versions() as $entry) {
            $entries[] = ['Key' => (string) $entry['Key'], 'VersionId' => (string) ($entry['VersionId'] ?? 'null')];
        }

        $errors = (new Objects($this->s3, $bucket))->deleteMany($entries, $bypassGovernanceRetention);
        if ($errors !== []) {
            $first = $errors[0];
            throw new RustFsException(sprintf('Could not empty bucket [%s]: %d entries failed, first: %s %s', $bucket, count($errors), $first['Key'] ?? '', $first['Message'] ?? ''));
        }

        return count($entries);
    }

    public function location(string $bucket): string
    {
        return (string) ($this->s3->getBucketLocation(['Bucket' => $bucket])['LocationConstraint'] ?? '');
    }

    // ---------------------------------------------------------------- versioning

    /**
     * @return string "Enabled", "Suspended" or "" (never enabled)
     */
    public function versioning(string $bucket): string
    {
        return (string) ($this->s3->getBucketVersioning(['Bucket' => $bucket])['Status'] ?? '');
    }

    public function enableVersioning(string $bucket): void
    {
        $this->s3->putBucketVersioning(['Bucket' => $bucket, 'VersioningConfiguration' => ['Status' => 'Enabled']]);
    }

    public function suspendVersioning(string $bucket): void
    {
        $this->s3->putBucketVersioning(['Bucket' => $bucket, 'VersioningConfiguration' => ['Status' => 'Suspended']]);
    }

    // ---------------------------------------------------------------- object lock

    /**
     * @return array<string, mixed>|null null when object lock is not configured
     */
    public function objectLock(string $bucket): ?array
    {
        return $this->optional(fn () => $this->s3->getObjectLockConfiguration(['Bucket' => $bucket])['ObjectLockConfiguration'] ?? null);
    }

    /**
     * Set the default retention applied to new objects of an object-lock bucket.
     *
     * @param  string  $mode  "GOVERNANCE" or "COMPLIANCE"
     */
    public function putObjectLock(string $bucket, string $mode, ?int $days = null, ?int $years = null): void
    {
        $configuration = ['ObjectLockEnabled' => 'Enabled'];
        if ($days !== null || $years !== null) {
            $configuration['Rule'] = ['DefaultRetention' => array_filter(['Mode' => $mode, 'Days' => $days, 'Years' => $years], static fn ($v) => $v !== null)];
        }

        $this->s3->putObjectLockConfiguration(['Bucket' => $bucket, 'ObjectLockConfiguration' => $configuration]);
    }

    // ---------------------------------------------------------------- lifecycle (ILM)

    /**
     * @return list<array<string, mixed>>
     */
    public function lifecycle(string $bucket): array
    {
        return $this->optional(fn () => $this->s3->getBucketLifecycleConfiguration(['Bucket' => $bucket])['Rules'] ?? []) ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $rules  e.g. [['ID' => 'expire-logs', 'Status' => 'Enabled', 'Filter' => ['Prefix' => 'logs/'], 'Expiration' => ['Days' => 30]]]
     */
    public function putLifecycle(string $bucket, array $rules): void
    {
        $this->s3->putBucketLifecycleConfiguration(['Bucket' => $bucket, 'LifecycleConfiguration' => ['Rules' => $rules]]);
    }

    public function deleteLifecycle(string $bucket): void
    {
        $this->s3->deleteBucketLifecycle(['Bucket' => $bucket]);
    }

    // ---------------------------------------------------------------- replication

    /**
     * @return array<string, mixed>|null
     */
    public function replication(string $bucket): ?array
    {
        return $this->optional(fn () => $this->s3->getBucketReplication(['Bucket' => $bucket])['ReplicationConfiguration'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $configuration  ['Role' => '', 'Rules' => [...]] - rule destinations use the ARN
     *                                               returned by Admin::replication()->setRemoteTarget()
     */
    public function putReplication(string $bucket, array $configuration): void
    {
        $this->s3->putBucketReplication(['Bucket' => $bucket, 'ReplicationConfiguration' => $configuration + ['Role' => '']]);
    }

    public function deleteReplication(string $bucket): void
    {
        $this->s3->deleteBucketReplication(['Bucket' => $bucket]);
    }

    // ---------------------------------------------------------------- encryption

    /**
     * @return array<string, mixed>|null
     */
    public function encryption(string $bucket): ?array
    {
        return $this->optional(fn () => $this->s3->getBucketEncryption(['Bucket' => $bucket])['ServerSideEncryptionConfiguration'] ?? null);
    }

    /**
     * Default server-side encryption: "AES256" (SSE-S3) or "aws:kms" (SSE-KMS with $kmsKeyId).
     */
    public function putEncryption(string $bucket, string $algorithm = 'AES256', ?string $kmsKeyId = null): void
    {
        $default = ['SSEAlgorithm' => $algorithm];
        if ($kmsKeyId !== null) {
            $default['KMSMasterKeyID'] = $kmsKeyId;
        }

        $this->s3->putBucketEncryption([
            'Bucket' => $bucket,
            'ServerSideEncryptionConfiguration' => ['Rules' => [['ApplyServerSideEncryptionByDefault' => $default]]],
        ]);
    }

    public function deleteEncryption(string $bucket): void
    {
        $this->s3->deleteBucketEncryption(['Bucket' => $bucket]);
    }

    // ---------------------------------------------------------------- policy

    /**
     * @return array<string, mixed>|null
     */
    public function policy(string $bucket): ?array
    {
        $policy = $this->optional(fn () => (string) $this->s3->getBucketPolicy(['Bucket' => $bucket])['Policy']);

        return $policy === null ? null : json_decode($policy, true);
    }

    /**
     * @param  array<string, mixed>|string  $policy
     */
    public function putPolicy(string $bucket, array|string $policy): void
    {
        $this->s3->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => is_array($policy) ? json_encode($policy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : $policy,
        ]);
    }

    /**
     * Allow anonymous read (GetObject) on the whole bucket or on a prefix.
     */
    public function makePublic(string $bucket, string $prefix = ''): void
    {
        $this->putPolicy($bucket, [
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/{$prefix}*"],
            ]],
        ]);
    }

    public function deletePolicy(string $bucket): void
    {
        $this->s3->deleteBucketPolicy(['Bucket' => $bucket]);
    }

    // ---------------------------------------------------------------- tagging

    /**
     * @return array<string, string>
     */
    public function tags(string $bucket): array
    {
        $tags = $this->optional(fn () => $this->s3->getBucketTagging(['Bucket' => $bucket])['TagSet'] ?? []) ?? [];

        return array_column($tags, 'Value', 'Key');
    }

    /**
     * @param  array<string, string>  $tags
     */
    public function putTags(string $bucket, array $tags): void
    {
        $this->s3->putBucketTagging(['Bucket' => $bucket, 'Tagging' => ['TagSet' => Tags::toTagSet($tags)]]);
    }

    public function deleteTags(string $bucket): void
    {
        $this->s3->deleteBucketTagging(['Bucket' => $bucket]);
    }

    // ---------------------------------------------------------------- CORS

    /**
     * @return list<array<string, mixed>>
     */
    public function cors(string $bucket): array
    {
        return $this->optional(fn () => $this->s3->getBucketCors(['Bucket' => $bucket])['CORSRules'] ?? []) ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $rules  e.g. [['AllowedMethods' => ['GET'], 'AllowedOrigins' => ['*'], 'AllowedHeaders' => ['*']]]
     */
    public function putCors(string $bucket, array $rules): void
    {
        $this->s3->putBucketCors(['Bucket' => $bucket, 'CORSConfiguration' => ['CORSRules' => $rules]]);
    }

    public function deleteCors(string $bucket): void
    {
        $this->s3->deleteBucketCors(['Bucket' => $bucket]);
    }

    // ---------------------------------------------------------------- event notifications

    /**
     * @return array<string, mixed>
     */
    public function notifications(string $bucket): array
    {
        $result = $this->s3->getBucketNotificationConfiguration(['Bucket' => $bucket])->toArray();
        unset($result['@metadata']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $configuration  e.g. ['QueueConfigurations' => [['QueueArn' => <an ARN from Admin::notifications()->arns()>,
     *                                               'Events' => ['s3:ObjectCreated:*'], 'Filter' => [...]]]]
     */
    public function putNotifications(string $bucket, array $configuration): void
    {
        $this->s3->putBucketNotificationConfiguration(['Bucket' => $bucket, 'NotificationConfiguration' => $configuration]);
    }

    /**
     * Subscribe a notification target ARN (see Admin::notifications()->arns()) to bucket events.
     *
     * @param  list<string>  $events
     */
    public function addNotification(string $bucket, string $arn, array $events = ['s3:ObjectCreated:*', 's3:ObjectRemoved:*'], string $prefix = '', string $suffix = ''): void
    {
        $configuration = $this->notifications($bucket);
        $rules = [];
        if ($prefix !== '') {
            $rules[] = ['Name' => 'prefix', 'Value' => $prefix];
        }
        if ($suffix !== '') {
            $rules[] = ['Name' => 'suffix', 'Value' => $suffix];
        }

        $entry = ['QueueArn' => $arn, 'Events' => $events];
        if ($rules !== []) {
            $entry['Filter'] = ['Key' => ['FilterRules' => $rules]];
        }
        $configuration['QueueConfigurations'] = [...($configuration['QueueConfigurations'] ?? []), $entry];

        $this->putNotifications($bucket, $configuration);
    }

    public function clearNotifications(string $bucket): void
    {
        $this->putNotifications($bucket, []);
    }

    /**
     * Run $callback and return null when the sub-resource is not configured.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function optional(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404 || str_starts_with((string) $e->getAwsErrorCode(), 'NoSuch')
                || in_array($e->getAwsErrorCode(), ['ObjectLockConfigurationNotFoundError', 'ServerSideEncryptionConfigurationNotFoundError', 'ReplicationConfigurationNotFoundError'], true)) {
                return null;
            }
            throw $e;
        }
    }
}
