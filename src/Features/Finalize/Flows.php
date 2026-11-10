<?php

namespace Tey\Kit\Features\Finalize;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;

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
        if ($flow->hasExtras()) {
            // Checked at boot: the schema covers exactly the declared fields, and a name another flow declares has the same schema, since the MCP
            // finalize tool publishes one `extra` object for every flow.
            $factory = new JsonSchemaTypeFactory;
            $mine = array_map(fn ($type): string => json_encode($type->toArray(), JSON_THROW_ON_ERROR), $flow->extraSchema($factory));
            foreach ($this->flows as $other) {
                foreach ($other->hasExtras() ? $other->extraSchema($factory) : [] as $field => $type) {
                    if (isset($mine[$field]) && $mine[$field] !== json_encode($type->toArray(), JSON_THROW_ON_ERROR)) {
                        throw new \RuntimeException("Finalize flows [{$other->name}] and [{$flow->name}] declare the extra [{$field}] with different schemas.");
                    }
                }
            }
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
