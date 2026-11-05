<?php

namespace Tey\Kit\Features\Finalize\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;

/** Finalizes (store) and un-finalizes (destroy) over the web channel: `FinalizeGate` operations, the same ones the MCP tools call. */
class FinalizationController
{
    use AnswersGate;

    public function store(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        $state = app(FinalizeGate::class)->finalize($this->principal($request), $flow, $subject, $this->revision($request), $request->boolean('certified'));

        return $this->answer($request, $flow, $subject, $state, 'Finalized');
    }

    public function destroy(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        $state = app(FinalizeGate::class)->unfinalize($this->principal($request), $flow, $subject, $this->revision($request), (string) $request->input('reason', ''));

        return $this->answer($request, $flow, $subject, $state, 'Un-finalized');
    }
}
