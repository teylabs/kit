<?php

namespace Tey\Kit\Features\Core\Routing;

use Attribute;

/**
 * A controller reached without signing in, and why (a token in the link, a signed URL, a verified webhook). Tey\Kit\Testing\RouteInvariants::declaredPublic()
 * reads it, so a route with no auth middleware needs this declaration and a `signed` or `throttle` middleware, and a page can't become public by accident.
 * `open: true` declares an uncapped read instead (discovery metadata, the home page): allowed on GET and HEAD only.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PublicRoute
{
    public function __construct(public string $reason, public bool $open = false) {}
}
