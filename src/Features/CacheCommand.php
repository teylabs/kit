<?php

namespace Tey\Kit\Features;

use Illuminate\Console\Command;

/** A no-op since 0.18: the features are config, so nothing is cached. Kept, and still run by `php artisan optimize`, so deploys that call it keep working. */
final class CacheCommand extends Command
{
    public const NOTHING = 'nothing to cache since 0.18';

    protected $signature = 'kit:cache';

    protected $description = 'Nothing to cache since kit 0.18 (kept for deploys that run it)';

    public function handle(): int
    {
        $this->components->info(self::NOTHING);

        return self::SUCCESS;
    }
}
