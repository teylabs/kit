<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;
use Throwable;
use Tey\Kit\Features\Core\Principal;

/**
 * An MCP tool over one principal-first operation, gated by the capability it names (`#[Capability]`, or a `CAPABILITY` constant while migrating). It admits the call
 * through `ConsentGate`, refuses arguments `rules()` does not name as `unknown_arguments`, validates the rest and returns `call()`'s result as structured content, after
 * `ConsentGate::confirm()` lets it through; a refusal becomes a code (`ConsentGate::refusal()`). The tool checks no permission itself: the operation authorizes the principal. Its name defaults to the capability with `.` as `_`.
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

    /** What the person is asked to confirm before a destructive call; the description by default. */
    protected function summary(array $validated): string
    {
        return $this->description();
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
            $confirmation = $gate->confirm($this->name(), $principal, $request, $this->summary($validated));
            if ($confirmation !== null) {
                return $confirmation;
            }

            return Response::structured($this->call($principal, $workspace, $validated));
        } catch (Throwable $e) {
            return Response::error($gate->refusal($e)['error']);
        }
    }
}
