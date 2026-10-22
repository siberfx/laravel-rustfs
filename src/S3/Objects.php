<?php

declare(strict_types=1);

namespace Siberfx\RustFs\S3;

use Aws\ResultInterface;
use Aws\S3\Exception\S3Exception;
use Aws\S3\ObjectUploader;
use Aws\S3\PostObjectV4;
use Aws\S3\S3Client;
use DateTimeInterface;
use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Object level S3 operations on one bucket.
 */
class Objects
{
    public function __construct(
        private readonly S3Client $s3,
        public readonly string $bucket,
    ) {}

    /**
     * Same objects API on another bucket.
     */
    public function bucket(string $bucket): self
    {
        return new self($this->s3, $bucket);
    }

    // ---------------------------------------------------------------- write

    /**
     * Single request upload.
     *
     * @param  string|resource|StreamInterface  $body
     * @param  array<string, mixed>  $options  any PutObject argument (ContentType, Metadata, ServerSideEncryption,
     *                                         SSEKMSKeyId, ObjectLockMode, ObjectLockRetainUntilDate, StorageClass, ...)
     * @return array<string, mixed> ETag, VersionId, ...
     */
    public function put(string $key, mixed $body, array $options = []): array
    {
        return $this->result($this->s3->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $body] + $options));
    }

    /**
     * Upload a file path, resource or string, switching to multipart above $multipartThreshold bytes.
     *
     * @param  string|resource|StreamInterface  $source  a local file path or the contents
     * @param  array<string, mixed>  $params  extra PutObject/CreateMultipartUpload params
     * @return array<string, mixed>
     */
    public function upload(string $key, mixed $source, array $params = [], int $multipartThreshold = 16 * 1024 * 1024, int $partSize = 16 * 1024 * 1024, int $concurrency = 4): array
    {
        $body = is_string($source) && is_file($source) ? fopen($source, 'rb') : $source;

        $uploader = new ObjectUploader($this->s3, $this->bucket, $key, $body, 'private', [
            'params' => $params,
            'mup_threshold' => $multipartThreshold,
            'part_size' => $partSize,
            'concurrency' => $concurrency,
        ]);

        return $this->result($uploader->upload());
    }

    /**
     * @param  array<string, mixed>  $options  CopyObject arguments (MetadataDirective, Metadata, TaggingDirective, ...)
     * @return array<string, mixed>
     */
    public function copy(string $sourceKey, string $destinationKey, ?string $sourceBucket = null, ?string $sourceVersionId = null, array $options = []): array
    {
        $source = rawurlencode($sourceBucket ?? $this->bucket) . '/' . str_replace('%2F', '/', rawurlencode($sourceKey));
        if ($sourceVersionId !== null) {
            $source .= '?versionId=' . rawurlencode($sourceVersionId);
        }

        return $this->result($this->s3->copyObject(['Bucket' => $this->bucket, 'Key' => $destinationKey, 'CopySource' => $source] + $options));
    }

    // ---------------------------------------------------------------- read

    public function get(string $key, ?string $versionId = null): string
    {
        return (string) $this->stream($key, $versionId);
    }

    /**
     * @param  array<string, mixed>  $options  GetObject arguments (Range, SSECustomerKey, ...)
     */
    public function stream(string $key, ?string $versionId = null, array $options = []): StreamInterface
    {
        return $this->s3->getObject($this->target($key, $versionId) + $options)['Body'];
    }

    public function download(string $key, string $path, ?string $versionId = null): void
    {
        $this->s3->getObject($this->target($key, $versionId) + ['SaveAs' => $path]);
    }

    /**
     * @return array<string, mixed>|null metadata (ContentLength, ContentType, ETag, LastModified, Metadata, VersionId, ...) or null when missing
     */
    public function head(string $key, ?string $versionId = null): ?array
    {
        try {
            return $this->result($this->s3->headObject($this->target($key, $versionId)));
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function exists(string $key): bool
    {
        return $this->head($key) !== null;
    }

    /**
     * Iterate objects (Key, Size, LastModified, ETag, StorageClass) below a prefix.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function list(string $prefix = '', bool $recursive = true): Generator
    {
        $args = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        if (! $recursive) {
            $args['Delimiter'] = '/';
        }

        foreach ($this->s3->getPaginator('ListObjectsV2', $args) as $page) {
            foreach ($page['CommonPrefixes'] ?? [] as $common) {
                yield ['Prefix' => $common['Prefix'], 'IsDirectory' => true];
            }
            foreach ($page['Contents'] ?? [] as $object) {
                yield $object;
            }
        }
    }

    /**
     * Iterate object versions and delete markers below a prefix.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function versions(string $prefix = ''): Generator
    {
        foreach ($this->s3->getPaginator('ListObjectVersions', ['Bucket' => $this->bucket, 'Prefix' => $prefix]) as $page) {
            foreach ($page['Versions'] ?? [] as $version) {
                yield $version + ['IsDeleteMarker' => false];
            }
            foreach ($page['DeleteMarkers'] ?? [] as $marker) {
                yield $marker + ['IsDeleteMarker' => true];
            }
        }
    }

    // ---------------------------------------------------------------- delete

    /**
     * @return array<string, mixed>
     */
    public function delete(string $key, ?string $versionId = null, bool $bypassGovernanceRetention = false): array
    {
        $args = $this->target($key, $versionId);
        if ($bypassGovernanceRetention) {
            $args['BypassGovernanceRetention'] = true;
        }

        return $this->result($this->s3->deleteObject($args));
    }

    /**
     * Delete many keys (or ['Key' => ..., 'VersionId' => ...] entries) in batches of 1000.
     *
     * @param  iterable<string|array{Key: string, VersionId?: string}>  $keys
     * @return list<array<string, mixed>> errors reported by the server
     */
    public function deleteMany(iterable $keys, bool $bypassGovernanceRetention = false): array
    {
        $errors = [];
        $batch = [];

        foreach ($keys as $key) {
            $batch[] = is_array($key) ? $key : ['Key' => $key];
            if (count($batch) === 1000) {
                $errors = [...$errors, ...$this->deleteBatch($batch, $bypassGovernanceRetention)];
                $batch = [];
            }
        }
        if ($batch !== []) {
            $errors = [...$errors, ...$this->deleteBatch($batch, $bypassGovernanceRetention)];
        }

        return $errors;
    }

    /**
     * @param  list<array{Key: string, VersionId?: string}>  $batch  at most 1000 entries
     * @return list<array<string, mixed>>
     */
    private function deleteBatch(array $batch, bool $bypassGovernanceRetention): array
    {
        $args = ['Bucket' => $this->bucket, 'Delete' => ['Objects' => $batch, 'Quiet' => true]];
        if ($bypassGovernanceRetention) {
            $args['BypassGovernanceRetention'] = true;
        }

        return $this->s3->deleteObjects($args)['Errors'] ?? [];
    }

    /**
     * Delete every object below a prefix (current versions only).
     *
     * @return int number of deleted objects
     */
    public function deletePrefix(string $prefix): int
    {
        $keys = [];
        foreach ($this->list($prefix) as $object) {
            $keys[] = $object['Key'];
        }
        $this->deleteMany($keys);

        return count($keys);
    }

    // ---------------------------------------------------------------- tagging

    /**
     * @return array<string, string>
     */
    public function tags(string $key, ?string $versionId = null): array
    {
        return array_column($this->s3->getObjectTagging($this->target($key, $versionId))['TagSet'] ?? [], 'Value', 'Key');
    }

    /**
     * @param  array<string, string>  $tags
     */
    public function putTags(string $key, array $tags, ?string $versionId = null): void
    {
        $this->s3->putObjectTagging($this->target($key, $versionId) + ['Tagging' => ['TagSet' => Tags::toTagSet($tags)]]);
    }

    public function deleteTags(string $key, ?string $versionId = null): void
    {
        $this->s3->deleteObjectTagging($this->target($key, $versionId));
    }

    // ---------------------------------------------------------------- object lock (WORM)

    /**
     * @return array{Mode?: string, RetainUntilDate?: DateTimeInterface}|null
     */
    public function retention(string $key, ?string $versionId = null): ?array
    {
        try {
            return $this->s3->getObjectRetention($this->target($key, $versionId))['Retention'] ?? null;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404 || str_starts_with((string) $e->getAwsErrorCode(), 'NoSuch')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @param  string  $mode  "GOVERNANCE" or "COMPLIANCE"
     */
    public function putRetention(string $key, string $mode, DateTimeInterface $retainUntil, ?string $versionId = null, bool $bypassGovernanceRetention = false): void
    {
        $args = $this->target($key, $versionId) + ['Retention' => ['Mode' => $mode, 'RetainUntilDate' => $retainUntil]];
        if ($bypassGovernanceRetention) {
            $args['BypassGovernanceRetention'] = true;
        }

        $this->s3->putObjectRetention($args);
    }

    public function legalHold(string $key, ?string $versionId = null): bool
    {
        try {
            return ($this->s3->getObjectLegalHold($this->target($key, $versionId))['LegalHold']['Status'] ?? 'OFF') === 'ON';
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404 || str_starts_with((string) $e->getAwsErrorCode(), 'NoSuch')) {
                return false;
            }
            throw $e;
        }
    }

    public function putLegalHold(string $key, bool $on, ?string $versionId = null): void
    {
        $this->s3->putObjectLegalHold($this->target($key, $versionId) + ['LegalHold' => ['Status' => $on ? 'ON' : 'OFF']]);
    }

    // ---------------------------------------------------------------- presigned URLs

    /**
     * @param  array<string, mixed>  $options  GetObject arguments, e.g. ['ResponseContentDisposition' => 'attachment']
     */
    public function temporaryUrl(string $key, DateTimeInterface|string $expires = '+15 minutes', array $options = []): string
    {
        return $this->presign('GetObject', $key, $expires, $options);
    }

    /**
     * Presigned PUT URL for direct browser/client uploads.
     *
     * @param  array<string, mixed>  $options  PutObject arguments, e.g. ['ContentType' => 'image/png']
     */
    public function temporaryUploadUrl(string $key, DateTimeInterface|string $expires = '+15 minutes', array $options = []): string
    {
        return $this->presign('PutObject', $key, $expires, $options);
    }

    /**
     * Presigned POST policy for HTML form uploads.
     *
     * @param  array<string, string>  $formInputs
     * @param  list<array<mixed>|array<string, string>>  $conditions  e.g. [['starts-with', '$key', 'uploads/'], ['content-length-range', 0, 10485760]]
     * @return array{url: string, fields: array<string, string>}
     */
    public function presignedPost(array $formInputs = [], array $conditions = [], DateTimeInterface|string $expires = '+15 minutes'): array
    {
        $post = new PostObjectV4($this->s3, $this->bucket, $formInputs, [['bucket' => $this->bucket], ...$conditions], $expires);

        return ['url' => (string) $post->getFormAttributes()['action'], 'fields' => $post->getFormInputs()];
    }

    // ---------------------------------------------------------------- multipart

    /**
     * @param  array<string, mixed>  $options
     * @return string the upload id
     */
    public function createMultipartUpload(string $key, array $options = []): string
    {
        return (string) $this->s3->createMultipartUpload(['Bucket' => $this->bucket, 'Key' => $key] + $options)['UploadId'];
    }

    /**
     * @param  string|resource|StreamInterface  $body
     * @return array{PartNumber: int, ETag: string}
     */
    public function uploadPart(string $key, string $uploadId, int $partNumber, mixed $body): array
    {
        $result = $this->s3->uploadPart(['Bucket' => $this->bucket, 'Key' => $key, 'UploadId' => $uploadId, 'PartNumber' => $partNumber, 'Body' => $body]);

        return ['PartNumber' => $partNumber, 'ETag' => (string) $result['ETag']];
    }

    /**
     * Presigned URL for uploading one part from a client.
     */
    public function temporaryPartUploadUrl(string $key, string $uploadId, int $partNumber, DateTimeInterface|string $expires = '+1 hour'): string
    {
        return $this->presign('UploadPart', $key, $expires, ['UploadId' => $uploadId, 'PartNumber' => $partNumber]);
    }

    /**
     * @param  list<array{PartNumber: int, ETag: string}>  $parts
     * @return array<string, mixed>
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): array
    {
        usort($parts, static fn (array $a, array $b) => $a['PartNumber'] <=> $b['PartNumber']);

        return $this->result($this->s3->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]));
    }

    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        $this->s3->abortMultipartUpload(['Bucket' => $this->bucket, 'Key' => $key, 'UploadId' => $uploadId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function multipartUploads(string $prefix = ''): array
    {
        $uploads = [];
        foreach ($this->s3->getPaginator('ListMultipartUploads', ['Bucket' => $this->bucket, 'Prefix' => $prefix]) as $page) {
            $uploads = [...$uploads, ...($page['Uploads'] ?? [])];
        }

        return $uploads;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parts(string $key, string $uploadId): array
    {
        $parts = [];
        foreach ($this->s3->getPaginator('ListParts', ['Bucket' => $this->bucket, 'Key' => $key, 'UploadId' => $uploadId]) as $page) {
            $parts = [...$parts, ...($page['Parts'] ?? [])];
        }

        return $parts;
    }

    // ---------------------------------------------------------------- S3 Select / restore

    /**
     * Run an S3 Select SQL expression over a CSV/JSON/Parquet object and return the concatenated records.
     *
     * @param  array<string, mixed>  $input  InputSerialization, default CSV with a header row
     * @param  array<string, mixed>  $output  OutputSerialization, default JSON lines
     */
    public function select(string $key, string $expression, array $input = ['CSV' => ['FileHeaderInfo' => 'USE']], array $output = ['JSON' => ['RecordDelimiter' => "\n"]]): string
    {
        $result = $this->s3->selectObjectContent([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Expression' => $expression,
            'ExpressionType' => 'SQL',
            'InputSerialization' => $input,
            'OutputSerialization' => $output,
        ]);

        $records = '';
        foreach ($result['Payload'] as $event) {
            if (isset($event['Records'])) {
                $records .= (string) $event['Records']['Payload'];
            }
        }

        return $records;
    }

    /**
     * S3 Select returning decoded JSON rows.
     *
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    public function selectRows(string $key, string $expression, array $input = ['CSV' => ['FileHeaderInfo' => 'USE']]): array
    {
        $rows = [];
        foreach (explode("\n", $this->select($key, $expression, $input)) as $line) {
            if (trim($line) !== '' && is_array($row = json_decode($line, true))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Restore a transitioned (tiered) object for $days.
     */
    public function restore(string $key, int $days = 1, ?string $versionId = null): void
    {
        $this->s3->restoreObject($this->target($key, $versionId) + ['RestoreRequest' => ['Days' => $days]]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $options
     */
    private function presign(string $command, string $key, DateTimeInterface|string $expires, array $options): string
    {
        $cmd = $this->s3->getCommand($command, ['Bucket' => $this->bucket, 'Key' => $key] + $options);

        return (string) $this->s3->createPresignedRequest($cmd, $expires)->getUri();
    }

    /**
     * @return array<string, mixed>
     */
    private function target(string $key, ?string $versionId): array
    {
        $args = ['Bucket' => $this->bucket, 'Key' => $key];
        if ($versionId !== null) {
            $args['VersionId'] = $versionId;
        }

        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(ResultInterface $result): array
    {
        $data = $result->toArray();
        unset($data['@metadata']);

        return $data;
    }
}
