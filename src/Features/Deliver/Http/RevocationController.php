<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** Revokes a delivery's customer link. */
class RevocationController
{
    use AnswersDesk;

    public function store(Request $request, int $workspace, string $definition, string $subject, int $delivery): mixed
    {
        $state = app(DeliveryDesk::class)->revoke($this->principal($request), $definition, $subject, $delivery, $this->revision($request));

        return $this->answer($request, $definition, $subject, $state, 'Link revoked');
    }
}
