<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Filesystem;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Siberfx\RustFs\S3\Buckets;
use Siberfx\RustFs\S3\Objects;

/**
 * The "rustfs" disk: everything Laravel's S3 disk offers (temporaryUrl, temporaryUploadUrl, streams,
 * visibility, ...) plus direct access to the RustFS object and bucket APIs of the disk's bucket.
 */
class RustFsFilesystemAdapter extends AwsS3V3Adapter
{
    public function bucketName(): string
    {
        return (string) $this->config['bucket'];
    }

    /**
     * Object API (versions, tags, retention, legal hold, select, multipart...) bound to the disk bucket.
     * Keys passed here are NOT prefixed with the disk "root".
     */
    public function objects(): Objects
    {
        return new Objects($this->client, $this->bucketName());
    }

    public function buckets(): Buckets
    {
        return new Buckets($this->client);
    }

    /**
     * Key of $path inside the bucket, with the disk "root" applied.
     */
    public function key(string $path): string
    {
        return $this->prefixer->prefixPath($path);
    }

    /**
     * @param  array<string, string>  $tags
     */
    public function setTags(string $path, array $tags): void
    {
        $this->objects()->putTags($this->key($path), $tags);
    }

    /**
     * @return array<string, string>
     */
    public function getTags(string $path): array
    {
        return $this->objects()->tags($this->key($path));
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function versions(string $path = ''): \Generator
    {
        return $this->objects()->versions($this->key($path));
    }
}
