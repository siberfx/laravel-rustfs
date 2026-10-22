<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Siberfx\RustFs\RustFsManager;

class UserCommand extends Command
{
    protected $description = 'Manage RustFS IAM users';

    protected $signature = 'rustfs:user
        {action=list : list | create | delete | enable | disable | policy | info}
        {accessKey? : Access key of the user}
        {--secret= : Secret key for "create" (generated when omitted)}
        {--policy= : Canned policy to attach (create / policy)}
        {--connection= : RustFS connection name}';

    public function handle(RustFsManager $rustfs): int
    {
        $users = $rustfs->connection($this->option('connection'))->admin()->users();
        $action = (string) $this->argument('action');
        $accessKey = (string) $this->argument('accessKey');

        if ($action !== 'list' && $accessKey === '') {
            $this->components->error('An access key is required.');

            return self::INVALID;
        }

        switch ($action) {
            case 'list':
                $rows = [];
                foreach ($users->list() as $key => $user) {
                    $rows[] = [$key, $user['status'] ?? '-', $user['policyName'] ?? '-', implode(', ', (array) ($user['memberOf'] ?? []))];
                }
                $this->table(['Access key', 'Status', 'Policy', 'Groups'], $rows);
                break;

            case 'create':
                $secret = (string) ($this->option('secret') ?: Str::password(32, symbols: false));
                $users->create($accessKey, $secret, $this->option('policy') ?: null);
                $this->components->info("User [{$accessKey}] created.");
                if (! $this->option('secret')) {
                    $this->components->twoColumnDetail('Secret key', $secret);
                }
                break;

            case 'delete':
                $users->delete($accessKey);
                $this->components->info("User [{$accessKey}] deleted.");
                break;

            case 'enable':
                $users->enable($accessKey);
                $this->components->info("User [{$accessKey}] enabled.");
                break;

            case 'disable':
                $users->disable($accessKey);
                $this->components->info("User [{$accessKey}] disabled.");
                break;

            case 'policy':
                if (! $this->option('policy')) {
                    $this->components->error('Pass --policy=<name>.');

                    return self::INVALID;
                }
                $users->setPolicy($accessKey, (string) $this->option('policy'));
                $this->components->info("Policy [{$this->option('policy')}] set on [{$accessKey}].");
                break;

            case 'info':
                $this->line((string) json_encode($users->info($accessKey), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                break;

            default:
                $this->components->error("Unknown action [{$action}].");

                return self::INVALID;
        }

        return self::SUCCESS;
    }
}
