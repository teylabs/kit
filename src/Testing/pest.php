<?php

// Test support only, and not autoloaded: a host loads it from tests/Pest.php with
// `require __DIR__.'/../vendor/tey/kit/src/Testing/pest.php';`.

use Tey\Kit\Testing\CurrentWorkspace;
use Tey\Kit\Testing\InWorkspace;

if (! function_exists('withCurrentWorkspace')) {
    /** At the top of a Pest file: every test in it starts with a fresh workspace current, and none is current after it. */
    function withCurrentWorkspace(): mixed
    {
        return uses(InWorkspace::class);
    }
}

if (! function_exists('withoutCurrentWorkspace')) {
    /** Inside a test (or a beforeEach) of an anonymous or public path: leaves no workspace current. */
    function withoutCurrentWorkspace(): void
    {
        CurrentWorkspace::leave();
    }
}
