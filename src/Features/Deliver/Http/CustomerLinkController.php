<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** The customer's signed link. A bad signature, token, expiry or revocation is a 404, never a reason. */
class CustomerLinkController
{
    public function show(Request $request, int $delivery, string $token): Response
    {
        abort_unless(URL::hasValidSignature($request), 404);

        return app(DeliveryDesk::class)->open($delivery, $token);
    }
}
