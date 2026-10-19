<?php

namespace Tey\StarterBlueprint;

use Illuminate\Support\ServiceProvider;
use Tey\Mod\Facades\Mod;
use Tey\Mod\Generation\Stub;
use Tey\Mod\Scaffolds\Scaffold;

final class BlueprintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $blueprint = Blueprint::release(config('starter-blueprint.release', 'A'));
        Mod::layout(config('mod.layout', 'laravel'))->generates('starter-policy', in: 'Support/Starter', stub: Stub::file($blueprint->stubPath('policy.php.stub')));
        Mod::vendor('teylabs')->scaffold('starter-foundation', fn (Scaffold $recipe) => $recipe->named('BlueprintPolicy')->makes('starter-policy', as: 'policy'), command: 'starter:foundation');
    }
}
