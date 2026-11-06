<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** Cancels a scheduled delivery. */
class CancellationController
{
    use AnswersDesk;

    public function store(Request $request, int $workspace, string $definition, string $subject, int $delivery): mixed
    {
        $state = app(DeliveryDesk::class)->cancel($this->principal($request), $definition, $subject, $delivery, $this->revision($request));

        return $this->answer($request, $definition, $subject, $state, 'Cancelled');
    }
}
