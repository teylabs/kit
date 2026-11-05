<?php

namespace Tey\Kit\Features\Finalize\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;

/** A finalize gate for one subject, as JSON. The page that shows it is the app's. */
class FinalizeController
{
    use AnswersGate;

    public function show(Request $request, int $workspace, string $flow, string $subject): mixed
    {
        return response()->json(app(FinalizeGate::class)->state($this->principal($request), $flow, $subject)->toArray());
    }
}
