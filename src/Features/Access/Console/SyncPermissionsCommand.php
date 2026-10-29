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
        // A role the app does not use (no admin, say) is not a misconfiguration: say so, and which defaults it would have had.
        foreach ($report['skipped_grants'] as $role => $permissions) {
            $this->line("No '".$role."' role in this app (concierge.roles does not name it), so nothing to grant it: ".implode(', ', $permissions).'. Nothing to configure unless you want that role.');
        }
        foreach (['custom' => 'Custom grant kept', 'undeclared' => 'Undeclared permission kept'] as $key => $label) {
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
