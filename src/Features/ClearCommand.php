<?php

namespace Tey\Kit\Features;

use Illuminate\Console\Command;

/** Removes the kit's discovery cache (see DiscoveryCache). Runs with `php artisan optimize:clear`. */
final class ClearCommand extends Command
{
    protected $signature = 'kit:clear';

    protected $description = 'Remove the cached kit lineages and tables';

    public function handle(): int
    {
        DiscoveryCache::clear($this->laravel);
        $this->components->info('Kit features cache cleared.');

        return self::SUCCESS;
    }
}
