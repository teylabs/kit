<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Features\Workspaces\AdoptableTables;
use Tey\Kit\Features\Workspaces\Adoptions;
use Tey\Kit\FeatureSwitch;
use Tey\Kit\HostRequirements;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Maintenance;
use Throwable;

final class AdoptCommand extends Command
{
    use FailsClosed;

    protected $signature = 'kit:adopt {feature? : A feature installed before lifecycle lineages; none lists them} {action? : plan, apply or rollback} {--from= : The release it was installed from; the closest one by default} {--intent=} {--expected-plan=} {--exclusive-checkout} {--json : Print only JSON; a plan in kit:add\'s plan schema}';

    protected $description = 'Record a feature already on disk as its own lineage, in place: nothing the app has is overwritten';

    public function handle(): int
    {
        try {
            if (! $this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A trusted LifecycleHost binding is required.');
            }
            $host = $this->laravel->make(LifecycleHost::class);
            $lineages = new Lineages($host->lifecycle(), $host instanceof CatalogHost ? $host->catalog() : null);
            if ($this->argument('feature') === null) {
                return $this->listAdoptable($host);
            }
            $action = $this->argument('action');
            if ($action === null) {
                throw new RuntimeException('Name the action: php artisan kit:adopt '.$this->argument('feature').' plan (then apply, or rollback).');
            }
            if (! in_array($action, ['plan', 'apply', 'rollback'], true)) {
                throw new RuntimeException('Unsupported adoption action.');
            }
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
                $result = HostRequirements::guarded(Maintenance::diagnose($plan, $paths) + ['from' => $adoption['from'], 'files' => $adoption['files'], 'kept' => $adoption['kept'], 'behind' => $adoption['behind']], $plan, $this->laravel->basePath());
                foreach ($adoption['behind'] as $path => $release) {
                    $this->line('UPDATE: '.$path.' matches release '.$release.', not this one. The adoption claims it as it is; then php artisan kit:update plan --lineage='.$id.' brings it up to date.');
                }
                // The feature's host steps, as kit:add prints them: attention only, so a legacy table it points out never blocks the adoption.
                $requirements = $lineages->requirements($this->laravel->basePath(), [$id]);
                $result['attention'] = array_values(array_map(fn (array $step): string => $step['message'], array_filter($requirements['steps'], fn (array $step): bool => $step['status'] !== 'met')));
                foreach ($this->option('json') ? [] : $result['attention'] as $line) {
                    $this->line('ATTENTION: '.$line);
                }
                $apply = 'php artisan kit:adopt '.$id.' apply'.($this->option('from') !== null ? ' --from='.$this->option('from') : '').' --intent=kit-adopt-'.substr($plan->identity(), 7, 16).' --exclusive-checkout --expected-plan='.$plan->identity();
                $result['apply'] = $apply;
                if ($this->option('json')) {
                    $this->line(json_encode(self::addSchema($id, $result, $plan->files, $plan->target->identity(), $requirements), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                    return $result['current'] && $result['executable'] ? self::SUCCESS : self::FAILURE;
                }
                $this->line($result['current'] && $result['executable'] ? 'Nothing written. Apply with: '.$result['apply'] : 'Nothing written. The plan is not executable.');
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
                // As kit:add does: an adopted feature is on only when kit.features (if the app lists it) names it.
                $result['switched'] = FeatureSwitch::on($this->laravel->basePath(), [$id], config('kit.features'));
                foreach ($this->option('json') ? [] : $result['switched']['attention'] as $line) {
                    $this->warn('ATTENTION: '.$line);
                }
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->line(json_encode(['status' => 'refused', 'error' => $exception->getMessage(), 'hint' => Lineages::pendingHint($this->laravel->basePath(), $exception->getMessage()) ?? []], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }
            $this->error($exception->getMessage());
            foreach (Lineages::pendingHint($this->laravel->basePath(), $exception->getMessage()) ?? [] as $line) {
                $this->line($line);
            }

            return self::FAILURE;
        }
    }

    /**
     * With no feature: the catalog's features, those already adopted as lineages, and for each of the others how many of its files the app has on disk (what an
     * adoption would record in place). Read-only; the next step is `kit:adopt <feature> plan`.
     */
    private function listAdoptable(LifecycleHost $host): int
    {
        $catalog = $host instanceof CatalogHost ? $host->catalog() : Catalog::package();
        $root = $this->laravel->basePath();
        $adopted = array_keys(Lineages::adopted($root, $host->appId(), $catalog));
        $adoptable = [];
        foreach (array_diff($catalog->featureIds(), $adopted) as $id) {
            $files = array_keys(Blueprint::lineage($id, $catalog)['feature']['files'] ?? []);
            $present = count(array_filter($files, fn (string $path): bool => ! is_link($root.'/'.$path) && is_file($root.'/'.$path)));
            $adoptable[] = ['feature' => $id, 'files_present' => $present, 'files' => count($files), 'next' => 'php artisan kit:adopt '.$id.' plan'];
        }
        $tables = self::tableAdoptions();
        if ($this->option('json')) {
            $this->line(json_encode(['status' => 'list', 'adopted' => $adopted, 'adoptable' => $adoptable, 'tables' => $tables], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $this->line('Adopted: '.($adopted === [] ? 'none' : implode(', ', $adopted)));
        foreach ($adoptable as $row) {
            $this->line($row['feature'].': '.$row['files_present'].' of '.$row['files'].' files on disk. Next: '.$row['next']);
        }
        foreach ($tables as $row) {
            $this->line('Table '.$row['table'].' ('.$row['feature'].'): '.$row['status'].($row['conflicts'] === [] ? '' : ': '.implode('; ', $row['conflicts'])));
        }

        return self::SUCCESS;
    }

    /**
     * The host tables the access and settings migrations would meet (AdoptableTables), one row each for a table the host database has: `adopted`
     * once kit_adoptions records it, `adoptable` when it is compatible, `conflict` (naming its columns) when it is not. A database the kit cannot
     * read gives one `unreadable` row per feature, never an empty list that reads as nothing to adopt.
     *
     * @return list<array{table: string, feature: string, status: string, conflicts: list<string>}>
     */
    private static function tableAdoptions(): array
    {
        $rows = [];
        foreach (['access', 'settings'] as $feature) {
            try {
                $tables = AdoptableTables::inspect($feature);
                foreach ($tables['adopt'] as $table) {
                    $rows[] = ['table' => $table, 'feature' => $feature, 'status' => Adoptions::adopted($table) ? 'adopted' : 'adoptable', 'conflicts' => []];
                }
                foreach ($tables['conflicts'] as $table => $problems) {
                    $rows[] = ['table' => $table, 'feature' => $feature, 'status' => 'conflict', 'conflicts' => $problems];
                }
            } catch (Throwable $error) {
                $rows[] = ['table' => implode(', ', array_keys(AdoptableTables::specs($feature))), 'feature' => $feature, 'status' => 'unreadable', 'conflicts' => [$error->getMessage()]];
            }
        }

        return $rows;
    }

    /**
     * The adoption plan in kit:add's preview schema (one `adopt` row), with the adoption's own keys (current, from, adopted: each file's identical/edited/missing, kept, orphans, removals, apply) kept beside it.
     */
    private static function addSchema(string $id, array $result, array $files, string $target, array $requirements): array
    {
        $executable = $result['current'] && $result['executable'];
        $unmet = $result['unmet'] ?? [];
        $steps = [...$unmet, ...array_map(fn (array $step): string => $step['message'], array_filter($requirements['steps'], fn (array $step): bool => $step['status'] === 'unmet'))];
        $row = ['feature' => $id, 'action' => 'adopt', 'target' => $target, 'files' => $files, 'claimable' => $result['claimable'], 'compatibility' => [], 'executable' => $executable, 'migrations' => HostRequirements::migrations($files)];

        return ['status' => $executable ? 'ready' : 'blocked', 'plan' => $result['plan'], 'executable' => $executable, 'deferred' => [], 'features' => [$row], 'claim' => [], 'claimable' => $result['claimable'], 'missing_packages' => [], 'host_steps' => $steps, 'unmet' => $unmet, 'adoptions' => $result['adoptions'] ?? [], 'host_ui' => $result['host_ui'] ?? []]
            + $requirements
            + ['current' => $result['current'], 'from' => $result['from'], 'adopted' => $result['files'], 'kept' => $result['kept'], 'orphans' => $result['orphans'], 'removals' => $result['removals'], 'apply' => $result['apply']];
    }
}
