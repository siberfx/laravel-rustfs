<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Resources;

/**
 * Healing (erasure-code repair) of the whole cluster, a bucket or a prefix.
 */
class Heal extends Resource
{
    /**
     * Start (or poll, by passing the returned clientToken) a heal sequence.
     *
     * @param  array<string, mixed>  $options  heal options, e.g. ['recursive' => true, 'dryRun' => false, 'remove' => false, 'scanMode' => 1]
     * @return array<string, mixed>
     */
    public function start(
        ?string $bucket = null,
        ?string $prefix = null,
        array $options = [],
        ?string $clientToken = null,
        bool $forceStart = false,
        bool $forceStop = false,
    ): array {
        $path = '/v3/heal/';
        if ($bucket !== null && $bucket !== '') {
            $path .= $bucket;
            if ($prefix !== null && $prefix !== '') {
                $path .= '/' . ltrim($prefix, '/');
            }
        }

        $query = self::filter([
            'clientToken' => $clientToken,
            'forceStart' => $forceStart ?: null,
            'forceStop' => $forceStop ?: null,
        ]);

        return self::asArray($this->client->post($path, $query, $options));
    }

    /**
     * Poll a running heal sequence.
     *
     * @return array<string, mixed>
     */
    public function status(string $clientToken, ?string $bucket = null, ?string $prefix = null): array
    {
        return $this->start($bucket, $prefix, clientToken: $clientToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function stop(?string $bucket = null, ?string $prefix = null): array
    {
        return $this->start($bucket, $prefix, forceStop: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function backgroundStatus(): array
    {
        return self::asArray($this->client->post('/v3/background-heal/status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function mrfResponsibilities(): array
    {
        return self::asArray($this->client->get('/v4/heal/mrf/responsibilities'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function mrfResponsibilitiesAction(array $payload): mixed
    {
        return $this->client->post('/v4/heal/mrf/responsibilities/actions', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function replacementRecoveryStatus(): array
    {
        return self::asArray($this->client->get('/v4/heal/replacement-recovery'));
    }
}
