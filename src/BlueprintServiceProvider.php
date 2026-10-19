<?php

namespace Tey\StarterBlueprint;

use Illuminate\Support\ServiceProvider;
use Tey\Mod\Facades\Mod;
use Tey\Mod\Generation\Stub;
use Tey\Mod\Placement\PlacementContext;
use Tey\Mod\Scaffolds\Scaffold;

final class BlueprintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('mod.layout', 'laravel') !== 'laravel') {
            throw new \RuntimeException('Blueprint requires the unmodified laravel layout.');
        }
        $blueprint = Blueprint::release(config('starter-blueprint.release', 'A'));
        Mod::layout(config('mod.layout', 'laravel'))->generates('starter-policy', in: 'Support/Starter', stub: Stub::file($blueprint->stubPath('policy.php.stub')));
        Mod::vendor('teylabs')->scaffold('starter-foundation', fn (Scaffold $recipe) => $recipe->named('BlueprintPolicy')->makes('starter-policy', name: 'BlueprintPolicy', as: 'policy', ungrouped: true, when: function () use ($blueprint): bool {
            $placed = Mod::current()->place('starter-policy', 'BlueprintPolicy', PlacementContext::none());
            if ($placed->path() !== array_key_first($blueprint->manifest['generated']) || $placed->fqcn() !== 'App\\Support\\Starter\\BlueprintPolicy') {
                throw new \RuntimeException('Blueprint requires the unmodified laravel layout.');
            }

            return true;
        }), command: 'starter:foundation');
    }
}
