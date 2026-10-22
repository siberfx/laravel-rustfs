<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Bulk export / import of the whole IAM state (users, groups, policies, service accounts).
 */
class Iam extends Resource
{
    /**
     * @return string zip archive contents
     */
    public function export(): string
    {
        return (string) $this->client->send('GET', '/v3/export-iam')->getBody();
    }

    /**
     * @param  string  $zip  zip archive contents produced by {@see self::export()}
     */
    public function import(string $zip): mixed
    {
        return $this->client->put('/v3/import-iam', [], $zip);
    }
}
