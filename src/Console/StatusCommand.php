<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\HostRequirements;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Throwable;

final class StatusCommand extends Command
{
    protected $signature = 'kit:status {--json : Print the installed status as JSON}';

    protected $description = 'Show adopted features and local edits to owned files (read-only)';

    public function handle(): int
    {
        try {
            $status = (new Lifecycle)->status($this->laravel->basePath());
            $requirements = $status === null ? null : $this->requirements($status['blueprint_features']);
            if ($requirements !== null) {
                $status['requirements'] = $requirements;
            }
            if ($this->option('json')) {
                $this->line(json_encode($status ?? ['message' => 'No kit features adopted.', 'blueprint_features' => [], 'lineages' => []], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } elseif ($status === null) {
                $this->line('No kit features adopted.');
            } else {
                $this->line('Adopted features: '.implode(', ', $status['blueprint_features']));
                foreach ($status['lineages'] as $lineage) {
                    $this->line('Lineage: '.$lineage['name'].' '.$lineage['version']);
                    $this->line('Identity: '.$lineage['identity']);
                    foreach ($lineage['files'] as $path => $state) {
                        $this->line($path.': '.$state.($state === 'edited' ? ' (conflict on next update)' : ''));
                    }
                }
                foreach ($requirements === null ? [] : [...HostRequirements::attention($requirements), ...HostRequirements::lines($requirements)] as $line) {
                    $this->line($line);
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * The adopted features' host steps and npm requirements, checked now as kit:add checked them before the apply, so a host step that has since
     * gone unmet (an alias removed, a config edited) shows here too. Null when no adopted feature the catalog knows declares any.
     *
     * @param  list<string>  $features
     * @return array<string, mixed>|null
     */
    private function requirements(array $features): ?array
    {
        $host = $this->laravel->bound(LifecycleHost::class) ? $this->laravel->make(LifecycleHost::class) : null;
        $catalog = $host instanceof CatalogHost ? $host->catalog() : Catalog::package();
        $known = array_values(array_intersect($features, $catalog->featureIds()));
        if ($known === []) {
            return null;
        }
        $requirements = (new Lineages(new Lifecycle, $catalog))->requirements($this->laravel->basePath(), $known);

        return $requirements['steps'] === [] && $requirements['npm'] === [] ? null : $requirements;
    }
}
