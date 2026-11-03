<?php

namespace Tey\Kit\Testing;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Contracts\IsTenant;
use Tey\Concierge\Contracts\Workspace;

/**
 * Test support only. `uses(InWorkspace::class)` in a Pest file (or `use InWorkspace;` on a Laravel TestCase): each test starts with a fresh
 * workspace current (`$this->workspace`), and none is current after it. A test of an anonymous or public path calls
 * `$this->withoutCurrentWorkspace()` (or `withoutCurrentWorkspace()`), so the default never hides a missing workspace.
 */
trait InWorkspace
{
    protected (Model&IsTenant&Workspace)|null $workspace = null;

    /** Laravel's TestCase calls this before each test of a class that uses the trait. */
    protected function setUpInWorkspace(): void
    {
        $this->workspace = CurrentWorkspace::enter();
    }

    /** And this after it, before the application is torn down. */
    protected function tearDownInWorkspace(): void
    {
        CurrentWorkspace::leave();
        $this->workspace = null;
    }

    protected function withoutCurrentWorkspace(): static
    {
        CurrentWorkspace::leave();
        $this->workspace = null;

        return $this;
    }
}
