<?php

namespace Tey\Kit\Features\Finalize\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;

/** Finalizes (store) and un-finalizes (destroy) over the web channel: `FinalizeGate` operations, the same ones the MCP tools call. */
class FinalizationController
{
    use AnswersGate;

    public function store(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        $definition = app(Flows::class)->find($flow);
        // A flow without extras reads nothing beyond `certified`, as before. With them, a page visit passes only the declared fields (a stray one is
        // dropped) and a JSON caller passes its whole body bar the certify fields, so an undeclared name is still `unknown_arguments`.
        $extra = $definition?->hasExtras() === true
            ? array_diff_key(OperationForm::input($request, $definition->extraFields()), array_flip(['certified', 'revision']))
            : [];
        $state = app(FinalizeGate::class)->finalize($this->principal($request), $flow, $subject, $this->revision($request), $request->boolean('certified'), 'web', $extra);

        return $this->answer($request, $flow, $subject, $state, 'Finalized');
    }

    public function destroy(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        $state = app(FinalizeGate::class)->unfinalize($this->principal($request), $flow, $subject, $this->revision($request), (string) $request->input('reason', ''));

        return $this->answer($request, $flow, $subject, $state, 'Un-finalized');
    }
}
