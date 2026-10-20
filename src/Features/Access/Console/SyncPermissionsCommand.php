<?php

namespace Tey\Kit\Features\Access\Console;

use Illuminate\Console\Command;
use Tey\Kit\Features\Access\PermissionSync;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'kit:permissions:sync';

    protected $description = 'Create the declared permissions and grant their default roles; custom grants are kept.';

    public function handle(PermissionSync $sync): int
    {
        $report = $sync->run();
        $this->line(sprintf('%d created, %d granted.', count($report['created']), count($report['granted'])));
        foreach (['created' => 'Created', 'granted' => 'Granted'] as $key => $label) {
            foreach ($report[$key] as $entry) {
                $this->line($label.': '.$entry);
            }
        }
        foreach (['unconfigured_roles' => 'Default role not configured, skipped', 'custom' => 'Custom grant kept', 'undeclared' => 'Undeclared permission kept'] as $key => $label) {
            foreach ($report[$key] as $entry) {
                $this->warn($label.': '.$entry);
            }
        }
        foreach ($report['missing_roles'] as $role) {
            $this->error('Default role missing: '.$role.' (run concierge:sync-roles).');
        }

        return $report['missing_roles'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
