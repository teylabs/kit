<?php

namespace Tey\Kit\Features\Finalize\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Concerns\Toastable;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;

/** The gate's JSON and its writes over the web channel. Every one is a `FinalizeGate` operation, the same ones the MCP tools call. */
class FinalizeController
{
    use Toastable;

    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function show(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        return response()->json(app(FinalizeGate::class)->state($this->principal($request), $flow, $subject)->toArray());
    }

    public function confirm(Request $request, int $workspace, string $flow, string $subject, string $step): mixed
    {
        $state = app(FinalizeGate::class)->confirm($this->principal($request), $flow, $subject, $step, $this->revision($request));

        return $this->answer($request, $flow, $subject, $state, 'Confirmed');
    }

    public function finalize(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        $state = app(FinalizeGate::class)->finalize($this->principal($request), $flow, $subject, $this->revision($request), $request->boolean('certified'));

        return $this->answer($request, $flow, $subject, $state, 'Finalized');
    }

    public function unfinalize(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        $state = app(FinalizeGate::class)->unfinalize($this->principal($request), $flow, $subject, $this->revision($request), (string) $request->input('reason', ''));

        return $this->answer($request, $flow, $subject, $state, 'Un-finalized');
    }

    private function revision(Request $request): string
    {
        $revision = $request->input('revision');
        if (! is_string($revision) || $revision === '') {
            throw new \RuntimeException('finalize_stale');
        }

        return $revision;
    }

    private function answer(Request $request, string $flow, string $subject, mixed $state, string $toast): mixed
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
