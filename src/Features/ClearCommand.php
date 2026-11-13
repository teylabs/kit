<?php

namespace Tey\Kit\Features;

use Illuminate\Console\Command;

/** A no-op since 0.18, the pair of kit:cache. Kept, and still run by `php artisan optimize:clear`, so deploys that call it keep working. */
final class ClearCommand extends Command
{
    protected $signature = 'kit:clear';

    protected $description = 'Nothing to clear since kit 0.18 (kept for deploys that run it)';

    public function handle(): int
    {
        $this->components->info(CacheCommand::NOTHING);

        return self::SUCCESS;
    }
}
