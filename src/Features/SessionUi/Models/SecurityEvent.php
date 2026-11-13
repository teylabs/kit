<?php

namespace Tey\Kit\Features\SessionUi\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\SessionUi\Concerns\IsSecurityEvent;
use Tey\Kit\Features\SessionUi\Contracts\SecurityEvent as SecurityEventContract;

/**
 * The kit's security event model, on `kit_security_events`: the default for `kit.models.security_event` until the app adopts session-ui's domain
 * table (`kit:scaffold session-ui --adopt`), which gives it its own model on `security_events`. Deprecated since 0.19 for new code: reach it through
 * `Tey\Kit\Kit::model('security_event')`. The behaviour is Concerns\IsSecurityEvent.
 */
class SecurityEvent extends Model implements SecurityEventContract
{
    use IsSecurityEvent;

    protected $table = 'kit_security_events';
}
