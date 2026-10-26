<?php

namespace Tey\Kit\Features\Mcp;

use Attribute;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use ReflectionClass;
use RuntimeException;

/**
 * The workspace capability an MCP tool belongs to. Registered with `Registry::capabilityTools()`, the tool is absent from the list while the capability is off for the
 * person in the workspace or out of its plan, and a call to it anyway is refused as `capability_unavailable` or `entitlement_unavailable`. It gates presence only: the
 * operation behind the tool still authorizes the principal. `destructive` asks for confirmation in the chat before the call; `scope` names an optional narrow token scope.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Capability
{
    public function __construct(public readonly string $capability, public readonly bool $destructive = false, public readonly ?string $scope = null)
    {
    }

    /** The attribute on a tool class (or, while migrating, its `CAPABILITY` constant), or null for a tool that declares neither. */
    public static function of(object|string $tool): ?self
    {
        $class = new ReflectionClass($tool);
        $attributes = $class->getAttributes(self::class);
        if ($attributes === []) {
            $constant = $class->hasConstant('CAPABILITY') ? $class->getConstant('CAPABILITY') : null;
            if (! is_string($constant) || $constant === '') {
                return null;
            }
        }
        $capability = $attributes === [] ? new self($constant) : $attributes[0]->newInstance();
        $hint = $class->getAttributes(IsDestructive::class);
        // laravel/mcp's own destructive hint counts too, so the hint a client sees and the confirmation never disagree.
        if (! $capability->destructive && $hint !== [] && $hint[0]->newInstance()->value) {
            return new self($capability->capability, true, $capability->scope);
        }

        return $capability;
    }

    /**
     * Refuses a tool `Registry::capabilityTools()` cannot gate: one with no attribute, one that does not use `GatedByCapability`, one naming an undeclared capability,
     * or one whose `ABILITY` a feature gates by a different capability.
     *
     * @param  list<string>  $declared
     * @param  array<string, string>  $abilities  ability => capability
     */
    public static function check(string $tool, array $declared, array $abilities = []): void
    {
        $capability = self::of($tool);
        if ($capability === null) {
            throw new RuntimeException('A capability tool needs the #[Capability] attribute: '.$tool);
        }
        if (! in_array(GatedByCapability::class, class_uses_recursive($tool), true)) {
            throw new RuntimeException('A capability tool needs to use GatedByCapability: '.$tool);
        }
        if (! in_array($capability->capability, $declared, true)) {
            throw new RuntimeException('A capability tool needs a declared capability: '.$tool.' names '.$capability->capability);
        }
        $ability = defined($tool.'::ABILITY') ? $tool::ABILITY : null;
        if ($ability !== null && isset($abilities[$ability]) && $abilities[$ability] !== $capability->capability) {
            throw new RuntimeException('A capability tool\'s ability is gated by another capability: '.$tool.' names '.$capability->capability.', '.$ability.' is '.$abilities[$ability]);
        }
    }

    public function destructive(): bool
    {
        return $this->destructive;
    }
}
