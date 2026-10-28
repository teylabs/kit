<?php

namespace Tey\Kit\Features\Access\Console;

use Illuminate\Console\Command;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Operations\ManageCapabilities;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;

/** `kit:capability`: show what is switched on or off in a workspace, or switch a capability for the workspace or one person in it (as a named operator, with a reason). */
final class CapabilityCommand extends Command
{
    protected $signature = 'kit:capability {workspace : The workspace slug} {capability? : The capability} {state? : on, off or clear} {--user= : A person\'s id, to override only them} {--operator= : The operator\'s user id} {--reason= : Why}';

    protected $description = 'Show or change which capabilities are on for a workspace or a person in it';

    public function handle(): int
    {
        $workspace = Workspaces::query()->where('slug', $this->argument('workspace'))->first();
        if ($workspace === null) {
            $this->error('There is no workspace with that slug.');

            return self::FAILURE;
        }
        if ($this->argument('capability') === null) {
            foreach (app(TenantBoundary::class)->run((int) $workspace->getKey(), fn () => app(Capabilities::class)->overview($workspace)) as $row) {
                $this->line(($row['workspace'] ? 'on  ' : 'off ').$row['capability'].($row['people'] === [] ? '' : '  (people: '.json_encode($row['people']).')'));
            }

            return self::SUCCESS;
        }
        $state = (string) $this->argument('state');
        if (! in_array($state, ['on', 'off', 'clear'], true) || $this->option('operator') === null || trim((string) $this->option('reason')) === '') {
            $this->error('Give a state (on, off or clear), --operator=<user id> and --reason="...".');

            return self::FAILURE;
        }
        try {
            app(ManageCapabilities::class)->set(new Principal((int) $this->option('operator')), (string) $this->argument('capability'), (int) $workspace->getKey(), $this->option('user') === null ? null : (int) $this->option('user'), $state === 'clear' ? null : $state === 'on', (string) $this->option('reason'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Done.');

        return self::SUCCESS;
    }
}
