<?php

declare(strict_types=1);

namespace Siberfx\RustFs\S3;

/**
 * @internal
 */
final class Tags
{
    /**
     * @param  array<string, string>  $tags
     * @return list<array{Key: string, Value: string}>
     */
    public static function toTagSet(array $tags): array
    {
        $set = [];
        foreach ($tags as $key => $value) {
            $set[] = ['Key' => (string) $key, 'Value' => (string) $value];
        }

        return $set;
    }
}
