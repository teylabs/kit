<?php

namespace Tey\Kit\Tests\PhpStan;

use Closure;
use Tey\Kit\Blueprint;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;

/** A host at PHPStan level max: implementing the contract and reading the lifecycle's results needs no ignores or casts of its own. */
final class FixtureHost implements LifecycleHost
{
    public function lifecycle(): Lifecycle
    {
        return new Lifecycle;
    }

    public function appId(): string
    {
        return 'fixture';
    }

    /** @return array<string, string> */
    public function bindings(): array
    {
        return ['brand' => 'Fixture'];
    }

    /** @return array<string, string> */
    public function inputPaths(): array
    {
        return ['host-config' => 'config/kit.php', 'host-operations' => 'app/Kit/operations.php'];
    }

    public function operationCheck(): Closure
    {
        return fn (): bool => true;
    }

    /** @return list<string> */
    public function installedFeatures(string $root): array
    {
        return $this->lifecycle()->status($root)['blueprint_features'] ?? [];
    }

    /** @return list<string> */
    public function written(Blueprint $target, string $root): array
    {
        $plan = $this->lifecycle()->plan($root, $this->appId(), null, $target, $this->bindings(), $this->inputPaths());
        $result = $this->lifecycle()->apply($plan, null, $target, $this->inputPaths(), 'adopt', $this->operationCheck(), true);

        return $result['written'];
    }

    public function modVersion(): string
    {
        return $this->lifecycle()->runtime()['installed_mod_version'];
    }
}
