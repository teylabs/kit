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
    /**
     * @param  bool  $explicit  the tool is admitted only when the connection's consent named its `scope` explicitly: a blanket acts-as-me grant never covers it, and the
     *                          consent page offers it as an unticked checkbox
     * @param  string|null  $explains  what ticking that box lets the assistant do, in words for the person (required on an explicit tool; the tool's description is for agents)
     */
    public function __construct(public readonly string $capability, public readonly bool $destructive = false, public readonly ?string $scope = null, public readonly bool $explicit = false, public readonly ?string $explains = null)
    {
    }

    /** The attribute on a tool class, or null for a tool without one. A `CAPABILITY` constant is not read: hosts use that name for their own tool ids. */
    public static function of(object|string $tool): ?self
    {
        $class = new ReflectionClass($tool);
        $attributes = $class->getAttributes(self::class);
        if ($attributes === []) {
            return null;
        }
        $capability = $attributes[0]->newInstance();
        $hint = $class->getAttributes(IsDestructive::class);
        // laravel/mcp's own destructive hint counts too, so the hint a client sees and the confirmation never disagree.
        if (! $capability->destructive && $hint !== [] && $hint[0]->newInstance()->value) {
            return new self($capability->capability, true, $capability->scope, $capability->explicit, $capability->explains);
        }

        return $capability;
    }

    /**
     * Refuses a tool `Registry::capabilityTools()` cannot gate: one with no attribute, one that does not use `GatedByCapability`, one naming an undeclared capability,
     * one with no ability to admit it under (an `ABILITY` constant or the attribute's `scope`), or one whose `ABILITY` a feature gates by a different capability.
     *
     * An explicit tool also needs its `scope` to be a registered token scope and an `explains` text, else the consent page could not offer it honestly.
     *
     * @param  list<string>  $declared
     * @param  array<string, string>  $abilities  ability => capability
     * @param  list<string>  $scopes  the registered token scopes
     */
    public static function check(string $tool, array $declared, array $abilities = [], array $scopes = []): void
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
        // Fail closed: admitted on the bare MCP grant, a tool would leave the operation's own checks as its only gate.
        if ($ability === null && $capability->scope === null) {
            throw new RuntimeException('A capability tool needs an ABILITY or a scope to be admitted under: '.$tool);
        }
        if ($ability !== null && isset($abilities[$ability]) && $abilities[$ability] !== $capability->capability) {
            throw new RuntimeException('A capability tool\'s ability is gated by another capability: '.$tool.' names '.$capability->capability.', '.$ability.' is '.$abilities[$ability]);
        }
        if ($capability->explicit) {
            if ($capability->scope === null || ! in_array($capability->scope, $scopes, true)) {
                throw new RuntimeException('An explicit tool needs a scope that is a registered token scope: '.$tool);
            }
            if (! is_string($capability->explains) || trim($capability->explains) === '') {
                throw new RuntimeException('An explicit tool needs an explains text for the consent page: '.$tool);
            }
        }
    }

    /** Whether the tool is admitted only under an explicit grant of its scope. */
    public function explicit(): bool
    {
        return $this->explicit;
    }

    public function destructive(): bool
    {
        return $this->destructive;
    }
}
