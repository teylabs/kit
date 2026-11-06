<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** Sends now, or schedules with `send_at`, over the web channel. */
class DeliveryController
{
    use AnswersDesk;

    public function store(Request $request, int $workspace, string $definition, string $subject): mixed
    {
        $state = app(DeliveryDesk::class)->send($this->principal($request), $definition, $subject, $this->revision($request), $this->draft($request), $this->claimKey($request));

        return $this->answer($request, $definition, $subject, $state, $request->filled('send_at') ? 'Scheduled' : 'Sent');
    }
}
