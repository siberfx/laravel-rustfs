<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Console;

use Illuminate\Console\Command;
use Siberfx\RustFs\RustFsManager;

class InfoCommand extends Command
{
    protected $description = 'Show RustFS server, storage and usage information';

    protected $signature = 'rustfs:info
        {--connection= : RustFS connection name}
        {--json : Print the raw server info as JSON}';

    public function handle(RustFsManager $rustfs): int
    {
        $system = $rustfs->connection($this->option('connection'))->admin()->system();
        $info = $system->info();

        if ($this->option('json')) {
            $this->line((string) json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Mode', (string) ($info['mode'] ?? '-'));
        $this->components->twoColumnDetail('Region', (string) ($info['region'] ?? '-'));
        $this->components->twoColumnDetail('Deployment ID', (string) ($info['deploymentID'] ?? '-'));
        $this->components->twoColumnDetail('Buckets', (string) ($info['buckets']['count'] ?? '-'));
        $this->components->twoColumnDetail('Objects', (string) ($info['objects']['count'] ?? '-'));
        $this->components->twoColumnDetail('Used', self::bytes($info['usage']['size'] ?? null));

        $servers = $info['servers'] ?? [];
        if (is_array($servers) && $servers !== []) {
            $this->newLine();
            $this->table(['Endpoint', 'State', 'Version', 'Drives'], array_map(static fn (array $server) => [
                $server['endpoint'] ?? '-',
                $server['state'] ?? '-',
                $server['version'] ?? '-',
                is_array($server['drives'] ?? null) ? count($server['drives']) : '-',
            ], $servers));
        }

        return self::SUCCESS;
    }

    private static function bytes(mixed $bytes): string
    {
        if (! is_numeric($bytes)) {
            return '-';
        }

        $bytes = (float) $bytes;
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return sprintf('%.2f %s', $bytes, $units[$i]);
    }
}
