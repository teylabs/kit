<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Concerns\Toastable;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Deliver\Deliveries;

/** What the deliver controllers share: the signed-in principal, the posted revision and claim key, the draft, and the answer (JSON, or back with a toast). */
trait AnswersDesk
{
    use Toastable;

    protected function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    protected function revision(Request $request): string
    {
        $revision = $request->input('revision');
        if (! is_string($revision) || $revision === '') {
            throw new \RuntimeException('deliver_stale');
        }

        return $revision;
    }

    /** The request's claim key: the Idempotency-Key header, else `claim_key`. A retry with the same key never sends twice. */
    protected function claimKey(Request $request): string
    {
        return (string) ($request->header('Idempotency-Key') ?? $request->input('claim_key', ''));
    }

    /** @return array<string, mixed> */
    protected function draft(Request $request): array
    {
        return $request->only(['to', 'cc', 'to_other', 'cc_other', 'bcc', 'subject', 'message', 'send_at']);
    }

    protected function answer(Request $request, string $definition, string $subject, mixed $state, string $toast): mixed
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($state->toArray());
        }
        $this->toast($toast)->from('save');
        $delivery = app(Deliveries::class)->get($definition);
        $model = $delivery->find($subject);

        return back(fallback: ($model === null ? null : $delivery->urlFor($model)) ?? '/');
    }
}
