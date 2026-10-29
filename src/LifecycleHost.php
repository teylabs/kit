<?php

namespace Tey\Kit;

use Closure;

/** Explicit trusted application binding; no default operation check or executable JSON. */
interface LifecycleHost
{
    public function lifecycle(): Lifecycle;

    public function appId(): string;

    /** @return array<string, string> */
    public function bindings(): array;

    /**
     * Actual policy, implementation, lock, config and frontend binding files.
     *
     * @return array<string, string> each verification input's id => its path relative to the host root
     */
    public function inputPaths(): array;

    /** Must exercise real host operation and tenant acceptance. */
    public function operationCheck(): Closure;
}
