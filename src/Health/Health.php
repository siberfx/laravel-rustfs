<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Health;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Siberfx\RustFs\ConnectionConfig;
use Throwable;

/**
 * Unauthenticated liveness / readiness probes.
 */
class Health
{
    private readonly ClientInterface $http;

    public function __construct(private readonly ConnectionConfig $config, ?ClientInterface $http = null)
    {
        $this->http = $http ?? new Client($config->http);
    }

    /**
     * The process is up and serving HTTP.
     */
    public function live(): bool
    {
        return $this->probe('/health/live');
    }

    /**
     * The node is ready to serve requests (storage and IAM initialised).
     */
    public function ready(): bool
    {
        return $this->probe('/health/ready');
    }

    /**
     * The cluster has write quorum (MinIO compatible probe).
     */
    public function cluster(): bool
    {
        return $this->probe('/minio/health/cluster');
    }

    /**
     * The cluster has read quorum (MinIO compatible probe).
     */
    public function clusterRead(): bool
    {
        return $this->probe('/minio/health/cluster/read');
    }

    /**
     * Detailed health document served at /health.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        try {
            $response = $this->http->request('GET', $this->config->endpoint . '/health', [RequestOptions::HTTP_ERRORS => false]);
            $decoded = json_decode((string) $response->getBody(), true);

            return ['status' => $response->getStatusCode()] + (is_array($decoded) ? $decoded : []);
        } catch (Throwable $e) {
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function probe(string $path): bool
    {
        try {
            $response = $this->http->request('GET', $this->config->endpoint . $path, [RequestOptions::HTTP_ERRORS => false]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (Throwable) {
            return false;
        }
    }
}
