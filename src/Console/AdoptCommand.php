<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\CatalogHost;
use Tey\Kit\FeatureSwitch;
use Tey\Kit\HostRequirements;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Maintenance;
use Throwable;

final class AdoptCommand extends Command
{
    use FailsClosed;

    protected $signature = 'kit:adopt {feature : A feature installed before lifecycle lineages} {action : plan, apply or rollback} {--from= : The release it was installed from; the closest one by default} {--intent=} {--expected-plan=} {--exclusive-checkout} {--json : Print only JSON; a plan in kit:add\'s plan schema}';

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
