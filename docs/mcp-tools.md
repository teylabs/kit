# MCP tools

An MCP tool names the workspace capability it belongs to with `#[Tey\Kit\Features\Mcp\Capability]` and is registered with `Registry::capabilityTools()`. The kit's MCP server (`WorkspaceServer`) then lists it only while that capability is on for the person in the workspace and in the workspace's plan. A call made anyway, for example from an assistant holding an older list, is refused with `capability_unavailable` or `entitlement_unavailable`, not reported as an unknown tool. The rules are the same as for every other surface of a capability: off means absent.

The attribute gates presence and refusal only. Authority stays in the operation behind the tool: the operation authorizes the principal with the same role and policy checks the UI uses, and the tool checks no permission itself.

## A tool

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.reports')]
final class ReadReport extends CapabilityTool
{
    protected string $description = 'Read one report by its id.';

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(Reports::class)->read($principal, $workspace, $validated['id']);
    }
}
```

`CapabilityTool` extends laravel/mcp's `Tool` and uses `GatedByCapability`. Its `handle()` does the following, in order:

1. Admits the call through `ConsentGate::admit()`. This yields the connection's principal, acting as its person, and the current workspace's id. Otherwise the call is refused as `connection_denied`.
2. Refuses any argument that `rules()` does not name, as `unknown_arguments`.
3. Validates the rest against `rules()`.
4. Asks `ConsentGate::confirm()` whether the call needs the person's confirmation in the chat. If it returns a response, that response is the tool's answer.
5. Returns `call()`'s array as structured content.

Any exception becomes a code through `ConsentGate::refusal()`.

The tool's name defaults to its capability with `.` replaced by `_` (`workspace_reports` above). A `$name` property or laravel/mcp's `#[Name]` overrides it. Give each tool its own name when one capability has several tools. `summary(array $validated)` is what the person is asked to confirm, and defaults to the description.

## The attribute

```php
#[Capability('workspace.payments', destructive: true, scope: 'money.write')]
```

- `capability`: a capability the Registry declares (`Registry::capabilities()`), declared before the tool is registered.
- `destructive`: the call asks for confirmation in the chat before it runs. laravel/mcp's own `#[IsDestructive]` on the tool counts too, so the hint a client sees and the kit's confirmation never disagree.
- `scope`: an optional narrow token scope (for example `money.read`) that the call is admitted under, in place of the one "acts as me" grant (`mcp:use`).

`Capability::of($tool)` reads the attribute from a class name or an instance, and returns null for a tool that declares none. `Capability::of($tool)?->destructive()` is what the consent gate reads.

## Registration

```php
app(Registry::class)->capabilityTools([ReadReport::class, PayInvoice::class]);
```

`capabilityTools()` fails closed at boot. It refuses each of these:

- a tool with no attribute;
- a tool that does not use `GatedByCapability`;
- a tool naming a capability that is not declared;
- any tool at all when the `mcp` feature is not adopted.

Tools are listed in registration order. Discovery by directory is not part of the kit yet.

`composer test:capability-guard` covers attribute-registered tools along with every other surface, with no MCP transport. It checks that each tool is listed while its capability is on and absent while it is off, and that a call to it while off is refused as `capability_unavailable`. `composer test:entitlements` checks that a tool whose capability is out of the plan is absent and refused as `entitlement_unavailable`.

## Error codes

A refused call returns a tool error whose text is one of these codes:

| Code | Meaning |
| --- | --- |
| `connection_denied` | No live, unrevoked connection acting for this person, or no current workspace. |
| `capability_unavailable` | The tool's capability is off for this person in this workspace. |
| `entitlement_unavailable` | The workspace's plan does not include the tool's capability. |
| `unknown_arguments` | An argument `rules()` does not name. |
| `validation_failed` | An argument failed `rules()`. |
| `not_found` | The operation found no such record (`ModelNotFoundException`). |
| `operation_failed` | Anything else. Details are never passed to the assistant. |

Codes the operation throws as a `RuntimeException` message pass through as they are when they are in `ConsentGate::CODES`: `permission_denied`, `workspace_not_found`, `account_inactive`, `verification_required`, `admission_required`, `stale_settings`, `intent_conflict`, `entitlement_required` and `limit_reached`. The confirmation codes are the consent gate's.

## Moving a hand-written tool onto the attribute

A tool that already extends a base with a `CAPABILITY` constant and the same template (`rules()`, plus `call(Principal, int $workspace, array $validated)`) moves over mechanically:

1. Extend `Tey\Kit\Features\Mcp\CapabilityTool` instead of the app's base. Its `handle()` is the template, so the tool keeps only its description, schema, `rules()` and `call()`.
2. Replace `public const CAPABILITY = 'x.y';` with `#[Capability('x.y')]`. Until then, `Capability::of()` also reads a non-empty `CAPABILITY` constant, so the tool works unchanged during the move.
3. Register the list with `capabilityTools([...])` in place of `tools([...])`.
4. Delete the app's own gate. The kit's `ConsentGate` admits, confirms and maps refusals in one place.

The tool's name is unchanged when it was already the capability with `.` replaced by `_`; otherwise keep it with `$name`. A plain laravel/mcp `Tool` that keeps its own `handle()` can also be registered: add the attribute and `use GatedByCapability;`. It is then listed and refused by capability like any other, and its `handle()` stays responsible for admitting the call (`Context::principal()`) and for its error codes.
