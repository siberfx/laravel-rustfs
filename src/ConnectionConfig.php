<?php

declare(strict_types=1);

namespace Siberfx\RustFs;

use Siberfx\RustFs\Exceptions\RustFsException;

/**
 * Immutable settings of one RustFS deployment.
 */
final readonly class ConnectionConfig
{
    /**
     * @param  array<string, mixed>  $http  Guzzle request options
     * @param  int  $adminRetries  retries of admin calls answered with 503 (e.g. usage not computed yet)
     */
    public function __construct(
        public string $endpoint,
        public string $key,
        public string $secret,
        public string $region = 'us-east-1',
        public ?string $token = null,
        public ?string $bucket = null,
        public bool $usePathStyleEndpoint = true,
        public string $adminPrefix = '/rustfs/admin',
        public array $http = [],
        public int $adminRetries = 3,
        public int $adminRetryDelayMs = 1000,
    ) {
        if ($endpoint === '') {
            throw new RustFsException('RustFS endpoint is not configured.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            endpoint: rtrim((string) ($config['endpoint'] ?? ''), '/'),
            key: (string) ($config['key'] ?? ''),
            secret: (string) ($config['secret'] ?? ''),
            region: (string) (($config['region'] ?? null) ?: 'us-east-1'),
            token: ($config['token'] ?? null) ?: null,
            bucket: ($config['bucket'] ?? null) ?: null,
            usePathStyleEndpoint: (bool) ($config['use_path_style_endpoint'] ?? true),
            adminPrefix: '/' . trim((string) (($config['admin_prefix'] ?? null) ?: '/rustfs/admin'), '/'),
            http: (array) ($config['http'] ?? []),
            adminRetries: max(0, (int) ($config['admin_retries'] ?? 3)),
            adminRetryDelayMs: max(0, (int) ($config['admin_retry_delay_ms'] ?? 1000)),
        );
    }

    /**
     * Options for Aws\S3\S3Client (also accepted by Laravel's s3 disk driver).
     *
     * @return array<string, mixed>
     */
    public function s3ClientOptions(): array
    {
        $credentials = ['key' => $this->key, 'secret' => $this->secret];
        if ($this->token !== null) {
            $credentials['token'] = $this->token;
        }

        return [
            'version' => 'latest',
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => $this->usePathStyleEndpoint,
            'credentials' => $credentials,
            // Only send/verify flexible checksums when an operation requires them
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'http' => $this->http,
        ];
    }
}
