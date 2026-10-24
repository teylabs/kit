<?php

namespace Tey\Kit\Console;

use Closure;
use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\Blueprint;
use Tey\Kit\CatalogHost;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\FilePlan;
use Tey\Mod\Maintenance\Maintenance;
use Throwable;

final class LifecycleCommand extends Command
{
    protected $signature = 'kit:update {action : plan, apply or resume} {--original=} {--target=C} {--features= : Comma-separated features for the target} {--original-features= : Comma-separated features already installed} {--intent=} {--expected-plan=} {--exclusive-checkout} {--lineage= : Update one adopted feature\'s own lineage}';

    protected $description = 'Explicit file-only blueprint maintenance using a trusted host binding';

    public function handle(): int
    {
        try {
            if (! $this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A trusted LifecycleHost binding is required.');
            }
            $action = $this->argument('action');
            if (! in_array($action, ['plan', 'apply', 'resume'], true)) {
                throw new RuntimeException('Unsupported maintenance action.');
            }
            $host = $this->laravel->make(LifecycleHost::class);
            $lifecycle = $host->lifecycle();
            $catalog = $host instanceof CatalogHost ? $host->catalog() : null;
            if ($this->option('lineage') !== null) {
                return $this->lineage($action, new Lineages($lifecycle, $catalog), $host);
            }
            // An app that adopted features updates each feature's own lineage; a release fixture or selection over it would install a second, unrelated artifact.
            $adopted = array_keys(Lineages::adopted($this->laravel->basePath(), $host->appId(), $catalog));
            if ($adopted !== []) {
                throw new RuntimeException("This app adopted features, so it updates each feature's lineage, in this order:\n".implode("\n", array_map(fn (string $id): string => '  php artisan kit:update plan --lineage='.$id, $adopted)));
            }
            $target = $this->option('features') === null ? Blueprint::release($this->option('target')) : Blueprint::features($this->list('features'), $catalog);
            $original = match (true) {
                $this->option('original-features') !== null => Blueprint::features($this->list('original-features'), $catalog),
                $this->option('original') !== null => Blueprint::release($this->option('original')),
                default => null,
            };
            $paths = $host->inputPaths();
            $root = $this->laravel->basePath();
            if ($action === 'resume') {
                $result = $lifecycle->resume($root, $host->appId(), $original, $target, $host->bindings(), $paths, (string) $this->option('intent'), $host->operationCheck(), (bool) $this->option('exclusive-checkout'));
            } else {
                $plan = $lifecycle->plan($root, $host->appId(), $original, $target, $host->bindings(), $paths);
                if ($action === 'plan') {
                    $result = Maintenance::diagnose($plan, $paths);
                    $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                    return $result['current'] && $result['executable'] ? self::SUCCESS : self::FAILURE;
                }
                if ($this->option('expected-plan') !== $plan->identity()) {
                    throw new RuntimeException('A matching freshly reviewed expected plan is required.');
                }
                $result = $this->failClosed($plan, (string) $this->option('intent'), fn (): array => $lifecycle->apply($plan, $original, $target, $paths, (string) $this->option('intent'), $host->operationCheck(), (bool) $this->option('exclusive-checkout')));
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

    /** Per-feature lineage: refused while any lineage has an unfinished update, except that lineage's own resume. */
    private function lineage(string $action, Lineages $lineages, LifecycleHost $host): int
    {
        if (! $this->option('exclusive-checkout') && $action !== 'plan') {
            throw new RuntimeException('Exclusive checkout and a stable, size-limited intent are required.');
        }
        $id = (string) $this->option('lineage');
        $root = $this->laravel->basePath();
        $paths = $host->inputPaths();
        if ($action === 'resume') {
            $result = $lineages->updateResume($root, $host->appId(), $id, $host->bindings(), $paths, (string) $this->option('intent'), $host->operationCheck());
        } else {
            $plan = $lineages->updatePlan($root, $host->appId(), $id, $host->bindings(), $paths);
            if ($action === 'plan') {
                $result = Maintenance::diagnose($plan, $paths) + ['files' => $plan->files];
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return $result['current'] && $result['executable'] ? self::SUCCESS : self::FAILURE;
            }
            if ($this->option('expected-plan') !== $plan->identity()) {
                throw new RuntimeException('A matching freshly reviewed expected plan is required.');
            }
            $result = $this->failClosed($plan, (string) $this->option('intent'), fn (): array => $lineages->updateApply($plan, $id, $paths, (string) $this->option('intent'), $host->operationCheck()));
        }
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * A fresh apply that does not complete leaves the checkout as it found it: every planned file is restored (or removed when the plan created it) and the
     * intent's journal goes, so nothing half-delivered stays behind and the next step is a fresh plan. Reopening an existing intent keeps Mod's resume.
     *
     * @param  Closure(): array  $apply
     */
    private function failClosed(FilePlan $plan, string $intent, Closure $apply): array
    {
        $journal = $plan->root.'/.mod-maintenance/journal-'.hash('sha256', $intent).'.json';
        if ($intent === '' || file_exists($journal)) {
            return $apply();
        }
        $before = [];
        foreach ($plan->files as $path => $entry) {
            if (($entry['state'] ?? null) !== 'unchanged') {
                $before[$path] = is_file($plan->root.'/'.$path) ? file_get_contents($plan->root.'/'.$path) : null;
            }
        }
        try {
            $result = $apply();
        } catch (Throwable $exception) {
            if (file_exists($journal)) {
                $this->restore($plan->root, $before, $journal);
            }
            throw $exception;
        }
        if ($result['status'] !== 'completed' && file_exists($journal)) {
            $result['rolled_back'] = $this->restore($plan->root, $before, $journal);
        }

        return $result;
    }

    /**
     * @param  array<string, ?string>  $before
     * @return list<string>
     */
    private function restore(string $root, array $before, string $journal): array
    {
        $restored = [];
        foreach ($before as $path => $contents) {
            // Mod validated every planned path as relative and inside the checkout.
            $file = $root.'/'.$path;
            $now = is_file($file) ? file_get_contents($file) : null;
            if ($now === $contents) {
                continue;
            }
            if ($contents === null) {
                unlink($file);
                // Drop the directories the apply created, up to the first one that holds anything else.
                for ($dir = dirname($file); $dir !== $root && is_dir($dir) && count(scandir($dir)) === 2; $dir = dirname($dir)) {
                    rmdir($dir);
                }
            } else {
                file_put_contents($file, $contents);
            }
            $restored[] = $path;
        }
        unlink($journal);

        return $restored;
    }

    /** @return list<string> */
    private function list(string $option): array
    {
        return array_map('trim', explode(',', (string) $this->option($option)));
    }
}
