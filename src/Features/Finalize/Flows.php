<?php

namespace Tey\Kit\Features\Finalize;

/** The app's finalize flows, by name. A singleton: register each flow once, in a provider. */
final class Flows
{
    /** @var array<string, Flow> */
    private array $flows = [];

    public function register(Flow $flow): self
    {
        $flow->assertComplete();
        if (isset($this->flows[$flow->name])) {
            throw new \RuntimeException('Finalize flow registered twice: '.$flow->name);
        }
        $this->flows[$flow->name] = $flow;

        return $this;
    }

    public function find(string $name): ?Flow
    {
        return $this->flows[$name] ?? null;
    }

    /** @return array<string, Flow> every registered flow, by name */
    public function all(): array
    {
        return $this->flows;
    }

    public function get(string $name): Flow
    {
        return $this->flows[$name] ?? throw new \RuntimeException('finalize_flow_unknown');
    }
}
