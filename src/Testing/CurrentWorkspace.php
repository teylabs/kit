<?php

namespace Tey\Kit\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Contracts\IsTenant;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Test support only: nothing in the kit's runtime references it. Creates a workspace and makes it the current tenant before a test, and
 * forgets it (spatie's `forgetCurrent`, which runs the switch tasks) after. `InWorkspace` and `withCurrentWorkspace()` call it.
 */
final class CurrentWorkspace
{
    /** @var (Closure(): (Model&IsTenant&Workspace))|null */
    private static ?Closure $factory = null;

    /** How a test workspace is created; null goes back to a plain row on the bound model with a name and a unique slug. */
    public static function createUsing(?Closure $factory): void
    {
        self::$factory = $factory;
    }

    /** Creates a workspace and makes it current, leaving no other workspace current first. */
    public static function enter(): Model&IsTenant&Workspace
    {
        self::leave();
        $workspace = self::$factory !== null ? (self::$factory)() : Workspaces::query()->create(['name' => 'Test workspace', 'slug' => 'test-'.Str::lower(Str::random(10))]);

        return $workspace->makeCurrent();
    }

    /** Leaves no workspace current. */
    public static function leave(): void
    {
        Workspaces::model()::forgetCurrent();
    }
}
