<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\CatalogHost;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Maintenance;
use Throwable;

final class AdoptCommand extends Command
{
    use FailsClosed;

    protected $signature = 'kit:adopt {feature : A feature installed before lifecycle lineages} {action : plan, apply or rollback} {--from= : The release it was installed from; the closest one by default} {--intent=} {--expected-plan=} {--exclusive-checkout}';

    protected $description = 'Record a feature already on disk as its own lineage, in place: nothing the app has is overwritten';

    public function handle(): int
    {
        try {
            if (! $this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A trusted LifecycleHost binding is required.');
            }
            $action = $this->argument('action');
            if (! in_array($action, ['plan', 'apply', 'rollback'], true)) {
                throw new RuntimeException('Unsupported adoption action.');
            }
            $host = $this->laravel->make(LifecycleHost::class);
            $lineages = new Lineages($host->lifecycle(), $host instanceof CatalogHost ? $host->catalog() : null);
            $id = (string) $this->argument('feature');
            $paths = $host->inputPaths();
            if ($action === 'rollback') {
                if (! $this->option('exclusive-checkout') || (string) $this->option('intent') === '') {
                    throw new RuntimeException('Exclusive checkout and the stopped adoption\'s --intent are required.');
                }
                $removed = $lineages->adoptRollback($this->laravel->basePath(), $host->appId(), $id, (string) $this->option('intent'));
                $this->line(json_encode(['status' => 'rolled_back', 'removed' => $removed, 'next' => 'php artisan kit:adopt '.$id.' plan'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }
            $adoption = $lineages->adoptPlan($this->laravel->basePath(), $host->appId(), $id, $host->bindings(), $paths, $this->option('from'));
            $plan = $adoption['plan'];
            if ($action === 'plan') {
                $result = Maintenance::diagnose($plan, $paths) + ['from' => $adoption['from'], 'files' => $adoption['files'], 'kept' => $adoption['kept']];
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return $result['current'] && $result['executable'] ? self::SUCCESS : self::FAILURE;
            }
            if (! $this->option('exclusive-checkout')) {
                throw new RuntimeException('Exclusive checkout and a stable, size-limited intent are required.');
            }
            if ($this->option('expected-plan') !== $plan->identity()) {
                throw new RuntimeException('A matching freshly reviewed expected plan is required.');
            }
            $result = $this->failClosed($plan, (string) $this->option('intent'), fn (): array => $lineages->adoptApply($plan, $id, $paths, (string) $this->option('intent'), $host->operationCheck(), $this->option('from')));
            if ($result['status'] === 'completed') {
                $result['next'] = 'php artisan kit:update plan --lineage='.$id;
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            foreach (Lineages::pendingHint($this->laravel->basePath(), $exception->getMessage()) ?? [] as $line) {
                $this->line($line);
            }

            return self::FAILURE;
        }
    }
}
