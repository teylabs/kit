<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Events\ToolCalled;
use Throwable;

/**
 * An MCP tool over one principal-first operation, gated by the capability it names (`#[Capability]`). It admits the call
 * through `ConsentGate`, refuses arguments `rules()` does not name as `unknown_arguments`, validates the rest and returns `call()`'s result as structured content, after
 * * `ConsentGate::confirm()` lets it through; a call carrying `intent_key` (in its rules) is stored, and a retry replays it (`ConsentGate::recall()`/`remember()`); a refusal becomes a code (`ConsentGate::refusal()`), and a call that ran dispatches `Events\ToolCalled`. The tool checks no permission itself: the operation authorizes the principal. Its name defaults to the capability with `.` as `_`.
 */
abstract class CapabilityTool extends Tool
{
    use GatedByCapability;

    /** @return array<string, mixed> the accepted arguments' validation rules; any other argument is refused */
    abstract protected function rules(): array;

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    abstract protected function call(Principal $principal, int $workspace, array $validated): array;

    /** The intent_key of the call now running, or null: the gate keeps it out of the operation's arguments, so a tool that needs it (as an idempotency key) reads it here. */
    private ?string $runningIntent = null;

    protected function intentKey(): ?string
    {
        return $this->runningIntent;
    }

    /** Whether this call needs the person's confirmation: the tool's destructive flag by default; override to confirm only some calls (keep the tool-level flag for the `destructiveHint`). */
    protected function destructive(array $validated): bool
    {
        return ConsentGate::destructive(static::class);
    }

    /** The code a record the operation cannot find is refused with. */
    protected function notFoundCode(): string
    {
        return 'not_found';
    }

    /** What the person is asked to confirm before a destructive call; the description by default. */
    protected function summary(array $validated): string
    {
        return $this->description();
    }

    /**
     * What an admitted call does, inside its intent's transaction: runs `call()` and dispatches `ToolCalled`. A queued tool (QueuedCapabilityTool) stores a run
     * and answers with its token here instead; everything before (consent, arguments, confirmation, replay) is the same for both.
     *
     * @param  array<string, mixed>  $operation  the validated arguments without the gate's control arguments
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function admitted(Principal $principal, int $workspace, array $operation, array $validated): array
    {
        return $this->perform($principal, $workspace, $operation, isset($validated['intent_key']) ? (string) $validated['intent_key'] : null);
    }

    /**
     * Runs `call()` with its intent key readable (`intentKey()`), then dispatches `ToolCalled`: only when it ran.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    final protected function perform(Principal $principal, int $workspace, array $operation, ?string $intent): array
    {
        $this->runningIntent = $intent;
        try {
            $result = $this->call($principal, $workspace, $operation);
        } finally {
            $this->runningIntent = null;
        }
        event(new ToolCalled($this->name(), static::class, $principal, $workspace, $operation, $result));

        return $result;
    }

    public function name(): string
    {
        if ($this->name !== '' || $this->resolveAttribute(Name::class) !== null) {
            return parent::name();
        }
        $capability = Capability::of($this)?->capability;

        return $capability === null ? parent::name() : str_replace('.', '_', $capability);
    }

    public function handle(Request $request): mixed
    {
        $gate = app(ConsentGate::class);
        try {
            $arguments = $request->all();
            // Fail closed: a tool with neither a scope nor an ability is never admitted (capabilityTools() refuses it at boot).
            $ability = Capability::of($this)?->scope ?? (defined(static::class.'::ABILITY') ? static::ABILITY : null);
            if ($ability === null) {
                throw new RuntimeException('connection_denied');
            }
            [$principal, $workspace] = $gate->admit($ability);
            // Fail closed: a call that reaches an absent tool is refused for the same reason it is absent, whatever the operation checks.
            $unavailable = $this->unavailable();
            if ($unavailable !== null) {
                throw new RuntimeException($unavailable);
            }
            $rules = $this->rules();
            if (array_diff(array_keys($arguments), array_keys($rules)) !== []) {
                throw new RuntimeException('unknown_arguments');
            }
            $validated = Validator::make($arguments, $rules)->validate();
            // A retry of a call that already ran (same intent_key and arguments) replays its result, without asking again; the key reused for other arguments is intent_conflict.
            $replayed = $gate->recall($this->name(), $principal, $validated);
            if ($replayed !== null) {
                return Response::structured($replayed);
            }
            // The control arguments stay with the gate; the operation never sees them.
            $operation = array_diff_key($validated, ['intent_key' => true, 'confirm_token' => true]);
            $confirmation = $gate->confirm($this->name(), $principal, $request, $this->summary($operation), $this->destructive($operation));
            if ($confirmation !== null) {
                return $confirmation;
            }

            // ToolCalled fires only when call() ran, inside the intent's transaction: never on a refusal, a confirmation prompt or a replay.
            return Response::structured($gate->remember($this->name(), $principal, $validated, fn (): array => $this->admitted($principal, $workspace, $operation, $validated)));
        } catch (Throwable $e) {
            $refusal = $gate->refusal($e, $this->notFoundCode());

            // The code stays the error text; detail (validation's field errors) rides along as structured content.
            return count($refusal) === 1 ? Response::error($refusal['error']) : Response::make([Response::error($refusal['error'])])->withStructuredContent($refusal);
        }
    }
}
