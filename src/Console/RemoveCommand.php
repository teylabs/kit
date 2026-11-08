<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\CatalogHost;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Throwable;

final class RemoveCommand extends Command
{
    protected $signature = 'kit:remove {feature : The installed feature to remove} {--force : Delete the unchanged files and the lineage record} {--json : Print the result as JSON}';

    protected $description = 'Remove an installed feature: lists its files by default; with --force deletes the ones still unchanged from the kit, and its lineage record';

    public function handle(): int
    {
        try {
            if (! $this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A trusted LifecycleHost binding is required.');
            }
            $host = $this->laravel->make(LifecycleHost::class);
            $lineages = new Lineages($host->lifecycle(), $host instanceof CatalogHost ? $host->catalog() : null);
            $root = $this->laravel->basePath();
            $plan = $lineages->removePlan($root, $host->appId(), (string) $this->argument('feature'), config('kit.features'));
            $result = $plan + ['removed' => false, 'deleted' => []];
            if ($this->option('force')) {
                $result['deleted'] = $lineages->removeApply($root, $host->appId(), $plan);
                $result['removed'] = true;
            }
            $this->option('json') ? $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) : $this->describe($result);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function describe(array $result): void
    {
        $verb = $result['removed'] ? 'deleted' : 'would delete';
        foreach ($result['removed'] ? $result['deleted'] : $result['delete'] as $path) {
            $this->line($verb.': '.$path);
        }
        foreach ($result['kept'] as $path) {
            $this->line('kept (changed since the kit wrote it, so it is yours): '.$path);
        }
        foreach ($result['missing'] as $path) {
            $this->line('already gone: '.$path);
        }
        $this->line($result['removed']
            ? $result['feature'].' removed: its lineage record '.$result['record'].' is deleted. Database tables its migrations created stay; drop them with a migration of your own if you no longer want them.'
            : 'Nothing written. Remove with: php artisan kit:remove '.$result['feature'].' --force');
    }
}
