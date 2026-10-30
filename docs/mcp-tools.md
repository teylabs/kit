# MCP tools

An MCP tool names the workspace capability it belongs to with `#[Tey\Kit\Features\Mcp\Capability]` and is registered with `Registry::capabilityTools()`. The kit's MCP server (`WorkspaceServer`) then lists it only while that capability is on for the person in the workspace and in the workspace's plan. A call made anyway, for example from an assistant holding an older list, is refused with `capability_unavailable` or `entitlement_unavailable`, not reported as an unknown tool. The rules are the same as for every other surface of a capability: off means absent.

The attribute gates presence and refusal only. Authority stays in the operation behind the tool: the operation authorizes the principal with the same role and policy checks the UI uses.

Two things decide whether a tool shows up, and they are kept apart:

- **Capability (the module switch):** the attribute names it. The tool is absent while the capability is off or out of plan.
- **Ability (the permission):** the `ABILITY` constant names it. The tool is also hidden from a person whose role lacks it, with the same rule Access applies: a permission a feature ties to the ability decides, and any other `.write` ability is owner-only. A call to it anyway is refused as `permission_denied`.

The capability is never derived from the tool's name or its ability.

How an assistant gets its connection is in [MCP consent](mcp-consent.md): the consent page, the workspace it's bound to, when consent is skipped, the one server address `/mcp`, and revoking. How it gets its OAuth client, and what the discovery documents advertise, is in [MCP client registration](mcp-registration.md).

## The server's name and instructions

Assistants see the workspace server as "Workspace operations", with the kit's instructions. To name it for your app, set:

```php
// config/kit.php
'mcp' => ['server' => ['name' => 'Acme workspace', 'instructions' => 'Manage Acme decks and their slides. Writes need the current version.']],
```

`KIT_MCP_SERVER_NAME` also sets the name. A null or blank value keeps the default.

`kit.mcp.server.page_size` (default 50) is how many tools, resources or prompts one list answer carries. Some assistants read the first `tools/list` answer only and never follow `nextCursor`, so laravel/mcp's default of 15 would hide every tool past the fifteenth from them; 50 is laravel/mcp's maximum, and a larger value is clamped to it.

## Sessions

Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment, not the platform default. Passport keeps the pending authorization request in the session during consent, and a cookie session passes the browser's 4 KB limit and is dropped without a word. On `SESSION_DRIVER=cookie` the consent routes (`passport.authorizations.authorize`, `approve` and `deny`) refuse with a 503 page naming the fix and log an error. The mcp feature's `config` host step flags a cookie driver in `kit:add` and `kit:update` plans as attention, for the environment the plan runs in. The tey/standards preflight `install.sessions` also checks it at deploy.

## A tool

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.reports')]
final class ReadReport extends CapabilityTool
{
    public const ABILITY = 'reports.read';

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
5. Refuses the call if the tool is absent here (capability, entitlement or ability, as above), before the operation runs.
6. Returns `call()`'s array as structured content. `call()` receives the validated arguments without `intent_key` and `confirm_token`, which stay with the gate.

Any exception becomes a code through `ConsentGate::refusal()`.

The tool's name defaults to its capability with `.` replaced by `_` (`workspace_reports` above). An explicit `$name` property or laravel/mcp's `#[Name]` always wins. Give each tool its own name when one capability has several tools. `summary(array $validated)` is what the person is asked to confirm, and defaults to the description.

A tool with a permission, its own name, and the web form's rules:

```php
#[Capability('workspace.projects')]
final class CreateProject extends CapabilityTool
{
    public const ABILITY = 'projects.write';

    protected string $name = 'projects_create';

    protected function rules(): array
    {
        return (new StoreProjectRequest)->rules();
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return CreateProjectOperation::run($principal, $validated)->toArray();
    }
}
```

`call()` always receives the workspace id. An operation that scopes ids itself can ignore it.

## The attribute

```php
#[Capability('workspace.payments', destructive: true, scope: 'money.write')]
```

- `capability`: a capability the Registry declares (`Registry::capabilities()`), declared before the tool is registered.
- `destructive`: the call asks for confirmation in the chat before it runs. laravel/mcp's own `#[IsDestructive]` on the tool counts too, so the hint a client sees and the kit's confirmation never disagree.
- `scope`: an optional narrow token scope (for example `money.read`) that the call is admitted under. Without it the call is admitted under `ABILITY`. A tool with neither is never admitted: it would pass on the bare MCP grant and leave the operation's own checks as its only gate.

`Capability::of($tool)` reads the attribute from a class name or an instance, and returns null for a tool that declares none. `Capability::of($tool)?->destructive()` is what the consent gate reads.

To confirm only some calls, override `protected function destructive(array $validated): bool` (it defaults to the tool-level flag), for example to confirm a `mode: delete` call and run the others straight away. Keep the tool-level flag when any call can be destructive, so the MCP `destructiveHint` still warns the client.

To refuse a missing record with another code, override `protected function notFoundCode(): string` (default `not_found`), for example `workspace_not_found`.

## Registration

```php
app(Registry::class)->capabilityTools([ReadReport::class, PayInvoice::class]);
```

`capabilityTools()` fails closed at boot. It refuses each of these:

- a tool with no attribute;
- a tool that does not use `GatedByCapability`;
- a tool naming a capability that is not declared;
- a tool with no `ABILITY` and no `scope` to be admitted under;
- a tool whose `ABILITY` a feature gates by a different capability;
- any tool at all when the `mcp` feature is not adopted.

Tools are listed in registration order. Discovery by directory is not part of the kit yet.

`composer test:capability-guard` covers attribute-registered tools along with every other surface, with no MCP transport. It checks that each tool is listed while its capability is on and absent while it is off, and that a call to it while off is refused as `capability_unavailable`. `composer test:capability-tool` checks the main path through the gate:

- an admitted call returns structured content;
- unknown arguments, invalid arguments, a missing record, no live connection, an off capability and a lacking ability are each refused with their code;
- an explicit name wins;
- FormRequest rules validate the call.

`composer test:entitlements` checks that a tool whose capability is out of the plan is absent and refused as `entitlement_unavailable`.

## Resources

The workspace server also lists and serves MCP resources a host registers: documentation an assistant should read before writing (a schema, a payload format), or workspace-scoped content. Two forms:

```php
app(Registry::class)->mcpResources([
    // A laravel/mcp Resource class, as the attributes describe it. No #[Capability]: static, always listed.
    SlideSchemaResource::class,
    // A class gated by a capability: absent while it is off here or out of plan, like a tool. It uses GatedByCapability.
    LatestReportResource::class,
    // A descriptor: uri, name, read (a string or a laravel/mcp Response), and optionally mimeType, description, title and capability.
    ['uri' => 'acme://docs/readme', 'name' => 'readme', 'mimeType' => 'text/markdown', 'description' => 'How to use the server.', 'read' => fn () => file_get_contents(resource_path('mcp/readme.md'))],
    ['uri' => 'acme://reports/summary', 'name' => 'reports-summary', 'capability' => 'workspace.reports', 'read' => fn (Request $request, ?Workspace $workspace) => Reports::summary($workspace)],
]);
```

A read runs inside the connection's workspace, so `Workspaces::current()` (and the descriptor closure's second argument) is the workspace the assistant was connected to, and the content is that workspace's. The admission is the tools': `resources/list` leaves out a gated resource whose capability is off for the person in the workspace or out of its plan; a `resources/read` of it anyway is refused with that reason (`capability_unavailable`, `entitlement_unavailable`) rather than reported as unknown, and a URI nothing registered is refused as not found. A resource with `#[Capability]` must use `GatedByCapability` and name a declared capability, a descriptor needs its uri (absolute), name and read, a URI registers once, and all of it needs the `mcp` feature: each is refused at registration. Resource templates (`HasUriTemplate`) are passed through to laravel/mcp as they are.

`composer test:mcp-resources` checks the list with the capability on and off, reads of static and gated resources (class and descriptor) with the workspace's content, the refusal of a gated read and of an unknown URI, the server's boot, and every registration refusal.

## Error codes

A refused call returns a tool error whose text is one of these codes:

| Code | Meaning |
| --- | --- |
| `connection_denied` | No live, unrevoked connection acting for this person, or no current workspace. |
| `capability_unavailable` | The tool's capability is off for this person in this workspace. |
| `entitlement_unavailable` | The workspace's plan does not include the tool's capability. |
| `permission_denied` | The person's role does not allow the tool's `ABILITY` (or the operation's own check refused it). |
| `unknown_arguments` | An argument `rules()` does not name. |
| `validation_failed` | An argument failed `rules()`. The response also carries the field errors as structured content: `{"error": "validation_failed", "errors": {"title": ["…"]}}`. |
| `not_found` | The operation found no such record (`ModelNotFoundException`); a tool can override the code with `notFoundCode()`. |
| `operation_failed` | Anything else. Details are never passed to the assistant; the exception is `report()`ed, so the host's exception handler logs it. Coded refusals, validation and not-found are not reported. |

Codes the operation throws as a `RuntimeException` message pass through as they are when they are in `ConsentGate::CODES`: `workspace_not_found`, `account_inactive`, `verification_required`, `admission_required`, `stale_settings`, `intent_conflict`, `entitlement_required` and `limit_reached`. The confirmation codes are the consent gate's. A coded `RuntimeException` always wins, whatever its subclass: a `ModelNotFoundException('workspace_not_found')` is `workspace_not_found`, not `not_found`.

A host's own domain codes pass through once registered, from a service provider:

```php
app(Registry::class)->mcpRefusalCodes(['stale_write', 'report_blocked']);
```

The operation then throws `new RuntimeException('stale_write')` and the assistant sees `stale_write`. An unregistered code is still `operation_failed`.

The error text is always the bare code. Structured content is added only when a refusal has detail (today, `validation_failed`), so clients reading the text keep working.

## After a call: `ToolCalled`

When `call()` has run and returned, the tool dispatches `Tey\Kit\Features\Mcp\Events\ToolCalled` with `tool` (the MCP name), `class`, `principal`, `workspaceId`, `arguments` (the validated arguments) and `result`. A host listener can write its own per-write audit from it:

```php
Event::listen(ToolCalled::class, fn (ToolCalled $call) => AssistantWrite::record($call->principal, $call->workspaceId, $call->tool, $call->arguments));
```

For a call carrying `intent_key` it fires inside the transaction that stores the intent, so a listener's writes commit or roll back with the call (including a racing retry that loses the unique index). Without `intent_key` there is no kit transaction and it fires right after `call()` returns.

It is not dispatched for a refusal, a `confirmation_required` prompt, or an intent replay: a replay is not a new action, and the original call already dispatched it. A hand-written tool that does not extend `CapabilityTool` dispatches nothing.

## Intent replay

A create or copy tool can make retries safe by accepting `intent_key` in `rules()`, e.g. `'intent_key' => ['sometimes', 'string', 'max:120']`. Its result is then stored. A retry with the same person, connection, `intent_key`, tool and arguments returns the stored result without running the operation again. That includes a destructive call that was already confirmed: the person isn't asked again. The same key with other arguments, or on another tool, is `intent_conflict`. A call without `intent_key` runs every time, and a call that fails stores nothing.

A stored result lasts `kit.mcp.intent_ttl` seconds (86400, 24 hours, by default). After that, its key is treated as new. Expired rows are removed by Laravel's model pruning, so schedule it in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
use Tey\Kit\Features\Mcp\Models\McpIntent;

Schedule::command('model:prune', ['--model' => [McpIntent::class]])->daily();
```

Without the schedule, replay still honours the expiry, but expired rows stay in `kit_mcp_intents` until their key is reused or their connection is deleted. A hand-written tool gets the same behaviour from `ConsentGate::recall()` (before `confirm()`) and `ConsentGate::remember()` (around the operation).

## Moving a hand-written tool onto the attribute

A tool that already extends a base with a `CAPABILITY` constant and the same template (`rules()`, plus `call(Principal, int $workspace, array $validated)`) moves over mechanically:

1. Extend `Tey\Kit\Features\Mcp\CapabilityTool` instead of the app's base. Its `handle()` is the template, so the tool keeps only its name, description, schema, `rules()` and `call()`. A tool whose handler validated a FormRequest returns that request's `rules()` from `rules()`, and moves its body into `call()`.
2. Add the attribute with the module's capability. Keep `ABILITY`, or add one if the tool has none: it stays the permission, and a tool needs it (or a `scope`) to be admitted. Replace `public const CAPABILITY = 'x.y';` with `#[Capability('x.y')]`. The kit never reads a `CAPABILITY` constant: a tool without the attribute stays gated by its `ABILITY` alone, as before 0.4, so a constant used for the app's own tool id is harmless.
3. Register the list with `capabilityTools([...])` in place of `tools([...])`.
4. Delete the app's own gate and any listing filter by role, which the kit now applies. The kit's `ConsentGate` admits, confirms and maps refusals in one place.

The tool's name is unchanged when it was already the capability with `.` replaced by `_`; otherwise keep it with `$name`. A plain laravel/mcp `Tool` that keeps its own `handle()` can also be registered: add the attribute and `use GatedByCapability;`. It is then listed and refused by capability like any other, and by its `ABILITY` (`permission_denied`) before its `handle()` runs. Its `handle()` stays responsible for admitting the call (`Context::principal()`) and for its other error codes.
