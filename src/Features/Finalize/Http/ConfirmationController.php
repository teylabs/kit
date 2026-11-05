<?php

namespace Tey\Kit\Features\Finalize\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;

/** Confirms a step over the web channel: a `FinalizeGate` operation, the same one the MCP tool calls. */
class ConfirmationController
{
    use AnswersGate;

    public function store(Request $request, int $workspace, string $flow, string $subject, string $step): mixed
    {
        $state = app(FinalizeGate::class)->confirm($this->principal($request), $flow, $subject, $step, $this->revision($request));

        return $this->answer($request, $flow, $subject, $state, 'Confirmed');
    }
}
