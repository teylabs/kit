<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Models\Workspace;

final class AccessProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->errors(['capability_unavailable' => 403, 'entitlement_required' => 403]);
    }

    public function boot(): void
    {
        foreach (app(Registry::class)->workspaceCapabilities() as $capability) {
            Feature::define($capability, fn (Workspace $workspace): bool => (bool) (config('kit.capabilities', [])[$capability] ?? false));
        }
    }
}
