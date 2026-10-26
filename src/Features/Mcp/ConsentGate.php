<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use RuntimeException;
use Throwable;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/** The one place a `CapabilityTool` call is admitted and confirmed, and how a refusal becomes the code the assistant sees. */
final class ConsentGate
{
    /** The codes an operation or the gate refuses with that reach the assistant as they are; anything else is `operation_failed`. */
    public const CODES = ['unknown_arguments', 'validation_failed', 'not_found', 'operation_failed', 'connection_denied', 'stale_settings', 'intent_conflict', 'permission_denied', 'workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'capability_unavailable', 'entitlement_unavailable', 'entitlement_required', 'limit_reached'];

    /**
     * The connection's principal acting as its person, and the current workspace's id, or `connection_denied`.
     *
     * @return array{0: Principal, 1: int}
     */
    public function admit(string $ability): array
    {
        $principal = Context::principal($ability);
        $workspace = Workspace::current();
        if ($workspace === null) {
            throw new RuntimeException('connection_denied');
        }

        return [$principal, (int) $workspace->getKey()];
    }

    /** A response that stops the call until the person confirms it in the chat, or null to go ahead. */
    public function confirm(string $tool, Principal $principal, Request $request, string $summary): ?ResponseFactory
    {
        return null;
    }

    /** @return array{error: string} */
    public function refusal(Throwable $e): array
    {
        return ['error' => match (true) {
            $e instanceof ValidationException => 'validation_failed',
            $e instanceof ModelNotFoundException => 'not_found',
            $e instanceof RuntimeException && in_array($e->getMessage(), self::CODES, true) => $e->getMessage(),
            default => 'operation_failed',
        }];
    }
}
