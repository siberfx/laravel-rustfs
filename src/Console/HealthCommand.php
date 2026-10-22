<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Console;

use Illuminate\Console\Command;
use Siberfx\RustFs\RustFsManager;

class HealthCommand extends Command
{
    protected $description = 'Check RustFS liveness, readiness and cluster quorum';

    protected $signature = 'rustfs:health {--connection= : RustFS connection name}';

    public function handle(RustFsManager $rustfs): int
    {
        $connection = $rustfs->connection($this->option('connection'));
        $health = $connection->health();

        $checks = [
            'live' => $health->live(),
            'ready' => $health->ready(),
            'cluster (write quorum)' => $health->cluster(),
            'cluster (read quorum)' => $health->clusterRead(),
        ];

        $this->components->info('RustFS ' . $connection->config()->endpoint);
        foreach ($checks as $name => $ok) {
            $this->components->twoColumnDetail($name, $ok ? '<fg=green>OK</>' : '<fg=red>FAIL</>');
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
