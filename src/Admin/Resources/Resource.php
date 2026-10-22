<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

use Siberfx\RustFs\Admin\AdminClient;

abstract class Resource
{
    public function __construct(protected readonly AdminClient $client) {}

    /**
     * Normalise a decoded answer to an array (null/empty bodies become []).
     *
     * @return array<mixed>
     */
    protected static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function filter(array $data): array
    {
        return array_filter($data, static fn (mixed $value) => $value !== null);
    }
}
