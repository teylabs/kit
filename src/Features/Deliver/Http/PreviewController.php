<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** The email as it would be sent with the posted draft. Nothing is written; the HTML is for a sandboxed frame only. */
class PreviewController
{
    use AnswersDesk;

    public function store(Request $request, int $workspace, string $definition, string $subject): mixed
    {
        return response()->json(app(DeliveryDesk::class)->preview($this->principal($request), $definition, $subject, $this->draft($request))->toArray());
    }
}
