<?php

namespace Tey\Kit\Features\Deliver;

/** The app's delivery definitions, by name. A singleton: register each once, in a provider. */
final class Deliveries
{
    /** @var array<string, Delivery> */
    private array $definitions = [];

    public function register(Delivery $delivery): self
    {
        $delivery->assertComplete();
        if (isset($this->definitions[$delivery->name])) {
            throw new \RuntimeException('Delivery registered twice: '.$delivery->name);
        }
        $this->definitions[$delivery->name] = $delivery;

        return $this;
    }

    public function get(string $name): Delivery
    {
        return $this->definitions[$name] ?? throw new \RuntimeException('deliver_definition_unknown');
    }

    /** @return array<string, Delivery> */
    public function all(): array
    {
        return $this->definitions;
    }
}
