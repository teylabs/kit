<?php

namespace Tey\Kit\Features\SessionUi\Concerns;

/** One entry of a user's own security history: the kit's behaviour for the security event model. It belongs to the user, never to a workspace, and is kept. */
trait IsSecurityEvent
{
    public function initializeIsSecurityEvent(): void
    {
        $this->timestamps = false;
        $this->guarded = [];
        $this->mergeCasts(['created_at' => 'datetime']);
    }
}
