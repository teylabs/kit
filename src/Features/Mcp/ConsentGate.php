<?php

namespace Tey\Kit\Features\Mcp;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use ReflectionClass;
use RuntimeException;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Throwable;

/**
 * The one consent gate every MCP tool goes through. `admit` checks the live token and the connection bound to this workspace cover the ability (acts-as-me covers every
 * ability, and Access's role, policy, capability and entitlement checks still decide); `confirm` is the in-chat two-step for destructive tools; `refusal` maps a failure to a
 * code the assistant can show. Audit entries made during the call are tagged "via assistant" by Audit itself.
 */
final class ConsentGate
{
    public const CONFIRM_TTL = 300;

    /** Codes passed through to the assistant as they are; anything else becomes `operation_failed`. */
    public const CODES = ['unknown_arguments', 'validation_failed', 'not_found', 'operation_failed', 'connection_denied', 'confirmation_invalid', 'stale_settings', 'intent_conflict', 'permission_denied',
        'workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'capability_unavailable', 'entitlement_unavailable', 'entitlement_required', 'limit_reached'];

    /** @return array{0: Principal, 1: int} */
    public function admit(string $ability): array
    {
        $principal = Context::principal($ability);
        $workspace = Workspace::current();
        if ($workspace === null) {
            throw new RuntimeException('connection_denied');
        }

        return [$principal, (int) $workspace->getKey()];
    }

    /** Whether a tool (its class, or its MCP name among the registered tools) is destructive: its `#[Capability(destructive: true)]` when it has one, else laravel/mcp's `#[IsDestructive]`. */
    public static function destructive(string $tool): bool
    {
        $tool = self::resolve($tool);
        $capability = Capability::of($tool);
        if ($capability !== null) {
            return $capability->destructive();
        }
        $attribute = (new ReflectionClass($tool))->getAttributes(IsDestructive::class)[0] ?? null;

        return $attribute !== null && $attribute->newInstance()->value;
    }

    /** A tool's class from its class name or its MCP name; an unknown tool fails closed. */
    private static function resolve(string $tool): string
    {
        if (is_subclass_of($tool, Tool::class)) {
            return $tool;
        }
        foreach (app(Registry::class)->mcpTools() as $class) {
            if (app($class)->name() === $tool) {
                return $class;
            }
        }
        throw new RuntimeException('operation_failed');
    }

    /**
     * Null when the call may go ahead. A destructive tool's first call instead returns `confirmation_required` with a single-use `confirm_token` bound to the person, the
     * connection, the tool and the exact arguments; only the same call carrying that token runs. A missing, reused, expired or mismatched token is `confirmation_invalid`.
     */
    public function confirm(string $tool, Principal $principal, Request $request, string $summary): Response|ResponseFactory|null
    {
        if (! self::destructive($tool)) {
            return null;
        }
        $arguments = $request->all();
        $given = $arguments['confirm_token'] ?? null;
        unset($arguments['confirm_token']);
        ksort($arguments);
        $binding = hash('sha256', json_encode([$principal->actorId, $principal->connectionId, self::resolve($tool), $arguments], JSON_THROW_ON_ERROR));
        if ($given !== null) {
            return is_string($given) && $given !== '' && Cache::pull('kit.mcp.confirm.'.hash('sha256', $given)) === $binding ? null : Response::error('confirmation_invalid');
        }
        $token = bin2hex(random_bytes(24));
        Cache::put('kit.mcp.confirm.'.hash('sha256', $token), $binding, self::CONFIRM_TTL);

        return Response::structured(['status' => 'confirmation_required', 'summary' => $summary, 'confirm_token' => $token, 'expires_in' => self::CONFIRM_TTL,
            'instructions' => 'Show the summary to the person and ask them to confirm. Only if they do, call this tool again with the same arguments and confirm_token.']);
    }

    /**
     * The stored result of an earlier call with the same intent: the same person and connection, `intent_key`, tool and arguments. Null when there is none (or the call
     * carries no `intent_key`); `intent_conflict` when the key was used for a different tool or different arguments.
     */
    public function recall(string $tool, Principal $principal, array $arguments): ?array
    {
        if (! is_string($arguments['intent_key'] ?? null) || $principal->connectionId === null) {
            return null;
        }
        $stored = DB::table('mcp_intents')->where('actor_id', $principal->actorId)->where('connection_id', $principal->connectionId)->where('intent_key', $arguments['intent_key'])->first();
        if ($stored === null) {
            return null;
        }
        if (! hash_equals($stored->fingerprint, self::fingerprint($tool, $arguments))) {
            throw new RuntimeException('intent_conflict');
        }

        return json_decode($stored->result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Runs a call and stores its result under its `intent_key`, in one transaction, so a retry replays it (`recall`) instead of running twice. Without an `intent_key` it just
     * runs. Two racing calls with one key: the unique index refuses the second's row, its work rolls back, and it returns the first's result, or `intent_conflict`.
     *
     * @param  Closure(): array  $run
     */
    public function remember(string $tool, Principal $principal, array $arguments, Closure $run): array
    {
        if (! is_string($arguments['intent_key'] ?? null) || $principal->connectionId === null) {
            return $run();
        }
        try {
            return DB::transaction(function () use ($tool, $principal, $arguments, $run): array {
                $result = $run();
                DB::table('mcp_intents')->insert(['actor_id' => $principal->actorId, 'connection_id' => $principal->connectionId, 'intent_key' => $arguments['intent_key'],
                    'fingerprint' => self::fingerprint($tool, $arguments), 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()]);

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->recall($tool, $principal, $arguments) ?? throw new RuntimeException('intent_conflict');
        }
    }

    /** The tool and its arguments, without the per-attempt `confirm_token`, in a stable order. */
    private static function fingerprint(string $tool, array $arguments): string
    {
        unset($arguments['confirm_token']);
        ksort($arguments);

        return hash('sha256', json_encode([self::resolve($tool), $arguments], JSON_THROW_ON_ERROR));
    }

    /** @return array{error: string} */
    public function refusal(Throwable $error): array
    {
        return ['error' => match (true) {
            $error instanceof RuntimeException && in_array($error->getMessage(), self::CODES, true) => $error->getMessage(),
            $error instanceof ValidationException => 'validation_failed',
            $error instanceof ModelNotFoundException => 'not_found',
            default => 'operation_failed',
        }];
    }
}
