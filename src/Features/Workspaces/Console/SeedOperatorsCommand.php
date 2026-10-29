<?php

namespace Tey\Kit\Features\Workspaces\Console;

use Illuminate\Console\Command;
use Tey\Kit\Features\Workspaces\Operators;

class SeedOperatorsCommand extends Command
{
    protected $signature = 'kit:operators:seed {--from-platform-owners : Seed from concierge.tenant_invitations.platform_owners} {--dry-run : Report what would be seeded; write nothing}';

    protected $description = 'Seed operators from the configured platform owners: verified, active users only; existing operators are kept.';

    public function handle(): int
    {
        if (! $this->option('from-platform-owners')) {
            $this->error('Name the source: --from-platform-owners.');

            return self::FAILURE;
        }
        if (Operators::platformOwners() === []) {
            $this->warn('concierge.tenant_invitations.platform_owners is empty; nothing to seed.');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $report = Operators::seed($dry);
        $this->line(sprintf('%s%d matched, %d already operators, %d skipped, %d unmatched.', $dry ? 'Dry run, nothing written: ' : '', count($report['matched']), count($report['operators']), count($report['skipped']), count($report['unmatched'])));
        foreach ($report['matched'] as $email => $id) {
            $this->line(($dry ? 'Would seed: ' : 'Seeded: ').$email.' (user '.$id.')');
        }
        foreach ($report['operators'] as $email => $id) {
            $this->line('Already an operator: '.$email.' (user '.$id.')');
        }
        foreach ($report['skipped'] as $email => $why) {
            $this->warn('Skipped, '.$why.': '.$email);
        }
        foreach ($report['unmatched'] as $email) {
            $this->warn('No user: '.$email);
        }

        return self::SUCCESS;
    }
}
