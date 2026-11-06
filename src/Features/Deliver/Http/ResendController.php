<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** Sends a delivery again as a new one, optionally revoking the earlier link. */
class ResendController
{
    use AnswersDesk;

    public function store(Request $request, int $workspace, string $definition, string $subject, int $delivery): mixed
    {
        $state = app(DeliveryDesk::class)->resend($this->principal($request), $definition, $subject, $delivery, $this->revision($request), $this->draft($request), $this->claimKey($request), $request->boolean('revoke_previous'));

        return $this->answer($request, $definition, $subject, $state, 'Sent again');
    }
}
