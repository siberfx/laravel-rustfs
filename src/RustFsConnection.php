<?php

declare(strict_types=1);

namespace Siberfx\RustFs;

use Aws\S3\S3Client;
use GuzzleHttp\ClientInterface;
use Siberfx\RustFs\Admin\Admin;
use Siberfx\RustFs\Admin\AdminClient;
use Siberfx\RustFs\Exceptions\RustFsException;
use Siberfx\RustFs\Health\Health;
use Siberfx\RustFs\S3\Buckets;
use Siberfx\RustFs\S3\Objects;

/**
 * One RustFS deployment: S3 data plane, Admin API and health probes.
 */
class RustFsConnection
{
    private ?S3Client $s3 = null;

    private ?Admin $admin = null;

    private ?Health $health = null;

    private ?Buckets $buckets = null;

    /**
     * @param  ClientInterface|null  $http  Guzzle client used for Admin and health calls (mainly for testing)
     */
    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly ?ClientInterface $http = null,
        ?S3Client $s3 = null,
    ) {
        $this->s3 = $s3;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): self
    {
        return new self(ConnectionConfig::fromArray($config));
    }

    public function config(): ConnectionConfig
    {
        return $this->config;
    }

    /**
     * The underlying AWS SDK client, for anything not wrapped by this package.
     */
    public function s3(): S3Client
    {
        return $this->s3 ??= new S3Client($this->config->s3ClientOptions());
    }

    public function buckets(): Buckets
    {
        return $this->buckets ??= new Buckets($this->s3());
    }

    /**
     * Object operations on $bucket (defaults to the connection bucket).
     */
    public function objects(?string $bucket = null): Objects
    {
        $bucket ??= $this->config->bucket;
        if ($bucket === null || $bucket === '') {
            throw new RustFsException('No bucket given and no default bucket configured for this RustFS connection.');
        }

        return new Objects($this->s3(), $bucket);
    }

    /**
     * Alias of {@see self::objects()}.
     */
    public function bucket(?string $bucket = null): Objects
    {
        return $this->objects($bucket);
    }

    public function admin(): Admin
    {
        return $this->admin ??= new Admin(new AdminClient($this->config, $this->http));
    }

    public function health(): Health
    {
        return $this->health ??= new Health($this->config, $this->http);
    }
}
