<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** Sends a test to the signed-in sender only. */
class TestDeliveryController
{
    use AnswersDesk;

    public function store(Request $request, int $workspace, string $definition, string $subject): mixed
    {
        $state = app(DeliveryDesk::class)->send($this->principal($request), $definition, $subject, $this->revision($request), $this->draft($request), $this->claimKey($request), test: true);

        return $this->answer($request, $definition, $subject, $state, 'Test sent to you');
    }
}
