<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Console;

use Illuminate\Console\Command;
use Siberfx\RustFs\RustFsManager;

class BucketCommand extends Command
{
    protected $description = 'List, create, delete or inspect RustFS buckets';

    protected $signature = 'rustfs:bucket
        {action=list : list | create | delete | info | versioning | quota}
        {bucket? : Bucket name}
        {value? : Value for the action (versioning: on|off, quota: size in bytes or "clear")}
        {--connection= : RustFS connection name}
        {--lock : Create with object lock (WORM) enabled}
        {--versioned : Enable versioning after creation}
        {--force : Delete every object version before deleting the bucket}';

    public function handle(RustFsManager $rustfs): int
    {
        $connection = $rustfs->connection($this->option('connection'));
        $buckets = $connection->buckets();
        $action = (string) $this->argument('action');
        $bucket = (string) $this->argument('bucket');

        if ($action !== 'list' && $bucket === '') {
            $this->components->error('A bucket name is required.');

            return self::INVALID;
        }

        switch ($action) {
            case 'list':
                $this->table(['Bucket', 'Created'], array_map(
                    static fn (array $b) => [$b['name'], $b['created']?->format('Y-m-d H:i:s') ?? '-'],
                    $buckets->list(),
                ));
                break;

            case 'create':
                $buckets->create($bucket, (bool) $this->option('lock'), (bool) $this->option('versioned'));
                $this->components->info("Bucket [{$bucket}] created.");
                break;

            case 'delete':
                if (! $this->confirmToProceed("Delete bucket [{$bucket}]" . ($this->option('force') ? ' and ALL of its objects' : '') . '?')) {
                    return self::FAILURE;
                }
                $buckets->delete($bucket, (bool) $this->option('force'));
                $this->components->info("Bucket [{$bucket}] deleted.");
                break;

            case 'info':
                $quota = rescue(fn () => $connection->admin()->quotas()->get($bucket), [], false);
                $this->components->twoColumnDetail('Versioning', $buckets->versioning($bucket) ?: 'Off');
                $this->components->twoColumnDetail('Object lock', $buckets->objectLock($bucket) !== null ? 'Enabled' : 'Off');
                $this->components->twoColumnDetail('Encryption', $buckets->encryption($bucket) !== null ? 'Enabled' : 'Off');
                $this->components->twoColumnDetail('Lifecycle rules', (string) count($buckets->lifecycle($bucket)));
                $this->components->twoColumnDetail('Public policy', $buckets->policy($bucket) !== null ? 'Yes' : 'No');
                $this->components->twoColumnDetail('Quota', isset($quota['quota']) && $quota['quota'] ? $quota['quota'] . ' bytes' : 'None');
                break;

            case 'versioning':
                $value = strtolower((string) $this->argument('value'));
                match ($value) {
                    'on', 'enable', 'enabled' => $buckets->enableVersioning($bucket),
                    'off', 'suspend', 'suspended' => $buckets->suspendVersioning($bucket),
                    default => null,
                };
                $this->components->info("Versioning of [{$bucket}]: " . ($buckets->versioning($bucket) ?: 'Off'));
                break;

            case 'quota':
                $value = (string) $this->argument('value');
                $quotas = $connection->admin()->quotas();
                if ($value === 'clear') {
                    $quotas->clear($bucket);
                } elseif (ctype_digit($value)) {
                    $quotas->set($bucket, (int) $value);
                }
                $this->line((string) json_encode($quotas->get($bucket), JSON_PRETTY_PRINT));
                break;

            default:
                $this->components->error("Unknown action [{$action}].");

                return self::INVALID;
        }

        return self::SUCCESS;
    }

    private function confirmToProceed(string $question): bool
    {
        return ! $this->input->isInteractive() || $this->components->confirm($question);
    }
}
