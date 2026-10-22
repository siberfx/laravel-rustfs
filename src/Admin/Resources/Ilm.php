<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\S3\Buckets;

/**
 * Manual ILM transitions, transition reconciliation and recovery records.
 *
 * Lifecycle rules themselves are S3 bucket configuration, see {@see Buckets::putLifecycle()}.
 */
class Ilm extends Resource
{
    /**
     * Transition matching objects to a tier now instead of waiting for the scanner.
     *
     * @param  array<string, mixed>  $request  e.g. ['bucket' => 'logs', 'prefix' => '2025/', 'tier' => 'COLD']
     * @return array<string, mixed> includes the job id
     */
    public function runTransition(array $request): array
    {
        return self::asArray($this->client->post('/v3/ilm/transition/run', [], $request));
    }

    /**
     * @return array<string, mixed>
     */
    public function transitionJob(string $jobId): array
    {
        return self::asArray($this->client->get('/v3/ilm/transition/jobs/' . $jobId));
    }

    public function cancelTransitionJob(string $jobId): mixed
    {
        return $this->client->delete('/v3/ilm/transition/jobs/' . $jobId);
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectReconcile(string $transactionId): array
    {
        return self::asArray($this->client->get('/v3/ilm/transition/reconcile/' . $transactionId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyReconcile(string $transactionId, array $payload = []): mixed
    {
        return $this->client->post('/v3/ilm/transition/reconcile/' . $transactionId, [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function recoveryRecords(array $query = []): mixed
    {
        return $this->client->get('/v3/ilm/recovery/records', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function recoveryRecord(string $controlId): array
    {
        return self::asArray($this->client->get('/v3/ilm/recovery/records/' . $controlId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateRecoveryRecord(string $controlId, array $payload): mixed
    {
        return $this->client->post('/v3/ilm/recovery/records/' . $controlId, [], $payload);
    }

    public function downloadRecoveryExport(string $exportId): string
    {
        return (string) $this->client->send('GET', '/v3/ilm/recovery/exports/' . $exportId)->getBody();
    }

    /**
     * @param  array<string, mixed>  $query  e.g. ['bucket' => ..., 'object' => ..., 'versionId' => ...]
     */
    public function inspectTransitionState(array $query): mixed
    {
        return $this->client->get('/v3/ilm/transition/state/reconcile', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     */
    public function applyTransitionState(array $query, array $payload = []): mixed
    {
        return $this->client->post('/v3/ilm/transition/state/reconcile', $query, $payload);
    }
}
