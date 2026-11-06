<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** The deliver state for a subject, as JSON. */
class DeliverController
{
    use AnswersDesk;

    public function show(Request $request, int $workspace, string $definition, string $subject): mixed
    {
        return response()->json(app(DeliveryDesk::class)->state($this->principal($request), $definition, $subject)->toArray());
    }
}
