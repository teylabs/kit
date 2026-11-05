<?php

namespace Tey\Kit\Features\Finalize\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Concerns\Toastable;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Finalize\Flows;

/** What the finalize controllers share: the signed-in principal, the posted revision, and the answer (JSON, or back with a toast). */
trait AnswersGate
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
            throw new \RuntimeException('finalize_stale');
        }

        return $revision;
    }

    protected function answer(Request $request, string $flow, string $subject, mixed $state, string $toast): mixed
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($state->toArray());
        }
        $this->toast($toast)->from('save');
        $definition = app(Flows::class)->get($flow);
        $model = $definition->find($subject);

        return back(fallback: ($model === null ? null : $definition->urlFor($model)) ?? '/');
    }
}
