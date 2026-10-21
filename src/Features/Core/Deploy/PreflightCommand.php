<?php

namespace Tey\Kit\Features\Core\Deploy;

use Illuminate\Console\Command;

/** The optional blocking gate of a deploy: tey/standards' full preflight under the kit's name, with a non-zero exit on a misconfigured production. Not part of the standard deploy lines. */
final class PreflightCommand extends Command
{
    protected $signature = 'kit:preflight {--skip=* : Check names to skip}';

    protected $description = 'Run the full deployment preflight and exit non-zero on a misconfigured production (optional; migrate and optimize are the whole deploy)';

    public function handle(): int
    {
        if (! array_key_exists('standards:preflight', $this->getApplication()?->all() ?? [])) {
            $this->error('tey/standards is not installed, so there is no preflight to run.');

            return self::FAILURE;
        }

        return $this->call('standards:preflight', array_filter(['--skip' => $this->option('skip')]));
    }
}
