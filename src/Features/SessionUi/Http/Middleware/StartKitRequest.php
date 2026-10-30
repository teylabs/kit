<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

/**
 * Deprecated: the kit's request start is core's, Tey\Kit\Features\Core\Http\Middleware\StartKitRequest, so it runs on a host without session-ui. This
 * old name stays an alias of it, for a host that names it (a route's withoutMiddleware); it is removed in a later release.
 */
class_alias(\Tey\Kit\Features\Core\Http\Middleware\StartKitRequest::class, StartKitRequest::class);
