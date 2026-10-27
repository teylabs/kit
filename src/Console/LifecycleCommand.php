<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\Blueprint;
use Tey\Kit\CatalogHost;
use Tey\Kit\HostRequirements;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\Maintenance\Maintenance;
use Throwable;

final class LifecycleCommand extends Command
{
    use FailsClosed;

    protected $signature = 'kit:update {action : plan, apply, resume, or refresh-executor (record this tey/mod in the executor provenance)} {--original=} {--target=C} {--features= : Comma-separated features for the target} {--original-features= : Comma-separated features already installed} {--intent=} {--expected-plan=} {--exclusive-checkout} {--lineage= : Update one adopted feature\'s own lineage} {--accept-changed-migrations : Apply a plan that replaces already-run migrations, once reviewed}';

    protected $description = 'Explicit file-only blueprint maintenance using a trusted host binding';

    public function handle(): int
    {
        try {
            if (! $this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A trusted LifecycleHost binding is required.');
            }
            $action = $this->argument('action');
            if (! in_array($action, ['plan', 'apply', 'resume', 'refresh-executor'], true)) {
                throw new RuntimeException('Unsupported maintenance action.');
            }
            $host = $this->laravel->make(LifecycleHost::class);
            if ($action === 'refresh-executor') {
                $refreshed = $host->lifecycle()->refreshExecutor($this->laravel->basePath(), $host->inputPaths());
                $this->line($refreshed['from'] === $refreshed['to'] ? 'The executor provenance already records tey/mod '.$refreshed['to'].'; nothing changed.' : 'The executor provenance now records tey/mod '.$refreshed['to'].' (was '.$refreshed['from'].'). Plan again.');

                return self::SUCCESS;
            }
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
            if ($this->option('original') === null && $this->option('original-features') === null && Checkout::installed($this->laravel->basePath(), $host->appId()) === []) {
                $before = (new Lineages($lifecycle, $catalog))->preLifecycle($this->laravel->basePath(), $host->appId());
                if ($before !== []) {
                    throw new RuntimeException("This app has kit feature files but no lifecycle records, so it was installed before lineages. Adopt each feature in place first, in this order:\n".implode("\n", array_map(fn (string $id): string => '  php artisan kit:adopt '.$id.' plan', $before)));
                }
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
                    $result = Maintenance::diagnose($plan, $paths) + self::migrationNotes($plan) + ['migrations' => HostRequirements::migrations($plan->files)];
                    if ($this->option('features') !== null) {
                        $result += (new Lineages($lifecycle, $catalog))->requirements($root, $this->list('features'));
                    }
                    $result = HostRequirements::guarded($result, $plan);
                    $selection = array_filter(['--original' => $this->option('original'), '--original-features' => $this->option('original-features'), '--target' => $this->option('features') === null ? $this->option('target') : null, '--features' => $this->option('features')], fn ($value): bool => $value !== null);
                    $result['apply'] = 'php artisan kit:update apply'.implode('', array_map(fn (string $option, string $value): string => ' '.$option.'='.$value, array_keys($selection), $selection)).' --expected-plan='.$plan->identity().' --exclusive-checkout --intent=kit-update-'.substr($plan->identity(), 7, 16);
                    $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                    return $result['current'] && $result['executable'] ? self::SUCCESS : self::FAILURE;
                }
                if ($this->option('expected-plan') !== $plan->identity()) {
                    throw new RuntimeException('A matching freshly reviewed expected plan is required.');
                }
                $this->refuseChangedMigrations($plan);
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
                // The step's own feature: its declarations are bound through the target artifact; their outcomes are reported, never bound.
                $result = Maintenance::diagnose($plan, $paths) + ['files' => $plan->files] + self::migrationNotes($plan) + ['migrations' => HostRequirements::migrations($plan->files)] + $lineages->requirements($root, [$id]);
                $result = HostRequirements::guarded($result, $plan);
                $pending = $lineages->pendingRequirements($root, $host->appId(), $id, $host->bindings());
                if ($pending !== []) {
                    // Each lineage is reviewed and applied on its own; a requirement whose update is still pending goes first.
                    $result['depends_on'] = $pending;
                    $result['order'] = 'Apply '.implode(', then ', $pending).' first: '.implode(', then ', array_map(fn (string $feature): string => 'php artisan kit:update plan --lineage='.$feature, [...$pending, $id])).'.';
                }
                $result['apply'] = 'php artisan kit:update apply --lineage='.$id.' --expected-plan='.$plan->identity().' --exclusive-checkout --intent=kit-update-'.$id.'-'.substr($plan->identity(), 7, 16);
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return $result['current'] && $result['executable'] ? self::SUCCESS : self::FAILURE;
            }
            if ($this->option('expected-plan') !== $plan->identity()) {
                throw new RuntimeException('A matching freshly reviewed expected plan is required.');
            }
            $this->refuseChangedMigrations($plan);
            $result = $this->failClosed($plan, (string) $this->option('intent'), fn (): array => $lineages->updateApply($plan, $id, $paths, (string) $this->option('intent'), $host->operationCheck()));
        }
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function list(string $option): array
    {
        return array_map('trim', explode(',', (string) $this->option($option)));
    }
}
