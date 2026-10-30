<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Inertia\Middleware;

/**
 * A plain Inertia middleware (the root view, the asset version and the validation errors), with nothing of the kit's in it. session-ui adds it to `web`
 * only when the host's `web` group has no Inertia middleware of its own; a host that lists it keeps working. It shares and flushes nothing: the kit's
 * props are shared through KitShares (at boot, and again in StartKitRequest and PrepareKitRequest), and its request hooks run in those two.
 */
class HandleInertiaRequests extends Middleware {}
