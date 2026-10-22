<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Psr\Http\Message\ResponseInterface;

/**
 * Admin-side bucket operations: metadata export/import, durability, on-demand migration,
 * orphan recovery and server-side zip downloads.
 */
class BucketAdmin extends Resource
{
    /**
     * Export bucket metadata (policies, lifecycle, versioning, replication, quota, tags, ...).
     *
     * @param  string|null  $bucket  null exports every bucket
     * @return string zip archive contents
     */
    public function exportMetadata(?string $bucket = null): string
    {
        return (string) $this->client->send('GET', '/v3/export-bucket-metadata', self::filter(['bucket' => $bucket]))->getBody();
    }

    /**
     * @param  string  $zip  zip archive produced by {@see self::exportMetadata()}
     */
    public function importMetadata(string $zip, ?string $bucket = null): mixed
    {
        return $this->client->put('/v3/import-bucket-metadata', self::filter(['bucket' => $bucket]), $zip);
    }

    /**
     * @return array<string, mixed>
     */
    public function durability(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/bucket-durability/' . $bucket));
    }

    /**
     * Override the durability mode of a bucket.
     *
     * @return array<string, mixed>
     */
    public function setDurability(string $bucket, string $mode): array
    {
        return self::asArray($this->client->put('/v3/bucket-durability/' . $bucket, [], ['mode' => $mode]));
    }

    /**
     * Drop the override; the bucket inherits the process-wide durability mode again.
     */
    public function resetDurability(string $bucket): mixed
    {
        return $this->client->delete('/v3/bucket-durability/' . $bucket);
    }

    /**
     * @return array<string, mixed>
     */
    public function migration(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/on-demand-migration/' . $bucket));
    }

    /**
     * Configure on-demand (lazy) migration of objects from a source S3 service into the bucket.
     *
     * @param  array<string, mixed>  $config  source endpoint, credentials, bucket, ...
     * @return array<string, mixed>
     */
    public function setMigration(string $bucket, array $config, bool $dryRun = false): array
    {
        return self::asArray($this->client->put('/v3/on-demand-migration/' . $bucket, $dryRun ? ['dryRun' => true] : [], $config));
    }

    public function deleteMigration(string $bucket): mixed
    {
        return $this->client->delete('/v3/on-demand-migration/' . $bucket);
    }

    /**
     * @return array<string, mixed>
     */
    public function migrationStatus(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/on-demand-migration/' . $bucket . '/status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function migrationBackfill(string $bucket): array
    {
        return self::asArray($this->client->get('/v3/on-demand-migration/' . $bucket . '/backfill'));
    }

    /**
     * Start / pause / resume / cancel the background backfill.
     *
     * @param  array<string, mixed>  $payload  e.g. ['action' => 'start']
     * @return array<string, mixed>
     */
    public function controlMigrationBackfill(string $bucket, array $payload): array
    {
        return self::asArray($this->client->post('/v3/on-demand-migration/' . $bucket . '/backfill', [], $payload));
    }

    /**
     * Recreate the metadata of a bucket whose data still exists on the drives.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recoverOrphaned(string $bucket, array $payload = []): mixed
    {
        return $this->client->post('/v3/recover-orphaned-bucket/' . $bucket, [], $payload);
    }

    /**
     * Ask the server to prepare a zip of several objects.
     *
     * @param  array<string, mixed>  $request  ['bucket' => 'photos', 'prefix' => '2026/'] plus optional 'objects' / 'prefixes' lists
     * @return array<string, mixed> download_url and expires_at
     */
    public function createZipDownload(array $request): array
    {
        return self::asArray($this->client->post('/v3/object-zip-downloads', [], $request));
    }

    /**
     * Download a prepared zip (signed).
     *
     * @param  string  $download  the download id, or the download_url returned by {@see self::createZipDownload()}
     * @param  array<string, mixed>  $options  Guzzle options, e.g. ['sink' => '/tmp/export.zip']
     */
    public function downloadZip(string $download, array $options = []): ResponseInterface
    {
        $path = (string) (parse_url($download, PHP_URL_PATH) ?: $download);
        $id = basename($path, '.zip');

        return $this->client->send('GET', '/v3/object-zip-downloads/' . $id . '.zip', [], null, $options);
    }
}
