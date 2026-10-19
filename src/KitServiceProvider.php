<?php

namespace Tey\Kit;

use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Tey\Mod\Facades\Mod;
use Tey\Mod\Generation\Stub;
use Tey\Mod\Placement\PlacementContext;
use Tey\Mod\Scaffolds\Scaffold;

final class KitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('mod.layout', 'laravel') !== 'laravel') {
            throw new RuntimeException('Blueprint requires the unmodified laravel layout.');
        }
        $this->commands([Console\LifecycleCommand::class, Console\ListCommand::class]);
        $features = config('kit.features');
        $blueprint = $features ? Blueprint::features($features, $this->catalog()) : Blueprint::release(config('kit.release', 'A'));
        $members = [];
        foreach ($blueprint->manifest['generated'] as $path => $entry) {
            $member = match ($path) {
                'app/Support/Starter/BlueprintPolicy.php' => ['starter-policy', 'BlueprintPolicy', 'policy.php.stub'],
                'app/Support/Starter/Defaults/BlueprintDefaults.php' => ['starter-defaults', 'BlueprintDefaults', 'defaults.php.stub'],
                default => throw new RuntimeException('Unsupported blueprint generated member.'),
            };
            if ($entry['stub'] !== $member[2] || $entry['scaffold'] !== 'kit' || $entry['ownership'] !== 'generated') {
                throw new RuntimeException('Unsupported blueprint member identity.');
            }
            $members[$path] = $member;
            Mod::layout('laravel')->generates($member[0], in: substr(dirname($path), 4), stub: Stub::file($blueprint->stubPath($member[2])));
        }
        // Every member validates the complete ownership set before generation starts.
        $validate = function () use ($members, $blueprint): bool {
            $blueprint->sourceReference();
            foreach ($members as $path => [$type, $name]) {
                $placed = Mod::current()->place($type, $name, PlacementContext::none());
                if ($placed->path() !== $path || $placed->fqcn() !== 'App\\'.str_replace('/', '\\', substr($path, 4, -4))) {
                    throw new RuntimeException('Blueprint requires the unmodified laravel layout.');
                }
            }

            return true;
        };
        Mod::vendor('tey')->scaffold('kit', function (Scaffold $recipe) use ($members, $validate): void {
            $recipe->named('BlueprintPolicy');
            foreach ($members as [$type, $name]) {
                $recipe->makes($type, name: $name, as: substr($type, 8), ungrouped: true, when: $validate);
            }
        }, command: 'kit:foundation');
    }

    /** The bound host's catalog when it has one, else the package catalog. */
    private function catalog(): ?Catalog
    {
        if (! $this->app->bound(LifecycleHost::class)) {
            return null;
        }
        $host = $this->app->make(LifecycleHost::class);

        return $host instanceof CatalogHost ? $host->catalog() : null;
    }
}
