<?php

namespace Tey\Kit\Features;

use Illuminate\Console\Command;
use Tey\Kit\LifecycleHost;
use Throwable;

/** Caches what the composition root reads from the installed lineage record (see DiscoveryCache). Runs with `php artisan optimize`. */
final class CacheCommand extends Command
{
    protected $signature = 'kit:cache';

    protected $description = 'Cache the adopted kit lineages and tables read from the installed record';

    public function handle(): int
    {
        DiscoveryCache::clear($this->laravel);
        $appId = config('kit.app_id') ?? ($this->laravel->bound(LifecycleHost::class) ? $this->laravel->make(LifecycleHost::class)->appId() : config('kit-lifecycle.app_id'));
        if (! is_string($appId) || $appId === '') {
            $this->components->info('No kit app id: nothing to cache.');

            return self::SUCCESS;
        }
        try {
            $entry = FeaturesServiceProvider::discover($this->laravel->basePath(), $appId);
        } catch (Throwable $error) {
            // Never cache a record that does not verify: requests keep reading it live and fail as they would without a cache.
            $this->components->warn('The installed record could not be read, so nothing was cached: '.$error->getMessage());

            return self::SUCCESS;
        }
        DiscoveryCache::write($this->laravel, $appId, $entry);
        $this->components->info('Kit features cached.');

        return self::SUCCESS;
    }
}
