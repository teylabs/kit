<?php

namespace Tey\Kit\Features\Access\Console;

use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Operations\ManageCapabilities;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * `kit:capability`: show what is switched on or off in a workspace, or switch a capability for the workspace or one person in it (as a named operator,
 * with a reason). It runs inside each workspace `--tenant=<id>` names; with none, in every workspace, as spatie's TenantAware commands do. The
 * workspace slug as the first argument is deprecated (removed in 0.6): it still selects that one workspace.
 */
final class CapabilityCommand extends Command
{
    use TenantAware {
        execute as executeForTenants;
    }

    protected $signature = 'kit:capability {workspace? : Deprecated: the workspace slug; use --tenant=<id>} {capability? : The capability} {state? : on, off or clear} {--tenant=* : The workspace ids; every workspace when none} {--user= : A person\'s id, to override only them} {--operator= : The operator\'s user id} {--reason= : Why}';

    protected $description = 'Show or change which capabilities are on for a workspace or a person in it';

    /** Whether the command runs for more than the one workspace it was given, so each listing is headed by its workspace. */
    private bool $several = true;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $first = $input->getArgument('workspace');
        if ($input->getOption('tenant') !== []) {
            // With --tenant the positional arguments are the capability and the state.
            if ($input->getArgument('state') !== null) {
                $this->error('Too many arguments: with --tenant, give only the capability and the state.');

                return self::FAILURE;
            }
            $input->setArgument('state', $input->getArgument('capability'));
            $input->setArgument('capability', $first);
            $input->setArgument('workspace', null);
            $this->several = count($input->getOption('tenant')) !== 1;
        } elseif ($first !== null) {
            $workspace = Workspaces::query()->where('slug', $first)->first();
            if ($workspace === null) {
                $this->error('There is no workspace with that slug.');

                return self::FAILURE;
            }
            $this->warn('The workspace argument is deprecated; use --tenant='.$workspace->getKey().'. It is removed in 0.6.');
            $input->setOption('tenant', [(string) $workspace->getKey()]);
            $this->several = false;
        }

        return $this->executeForTenants($input, $output);
    }

    public function handle(): int
    {
        $workspace = Workspaces::required();
        if ($this->argument('capability') === null) {
            if ($this->several) {
                $this->line('Workspace '.$workspace->slug.' ('.$workspace->getKey().'):');
            }
            foreach (app(Capabilities::class)->overview($workspace) as $row) {
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
            $this->error(($this->several ? $workspace->slug.': ' : '').$e->getMessage());

            return self::FAILURE;
        }
        $this->info($this->several ? 'Done in '.$workspace->slug.'.' : 'Done.');

        return self::SUCCESS;
    }
}
