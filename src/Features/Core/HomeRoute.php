<?php

namespace Tey\Kit\Features\Core;

use InvalidArgumentException;

/**
 * The name of the kit's home route (workspace-ui's, at `kit.home`): `kit.home_name`, `home` by default. A host that already has a route named `home` (its own public
 * landing page) sets another, such as `app.home`, and the kit's breadcrumbs root at that name. The `kit.routes.session-ui.name` prefix applies on top, as before.
 */
final class HomeRoute
{
    public static function name(): string
    {
        $name = config('kit.home_name', 'home');
        if (! is_string($name) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) !== 1) {
            throw new InvalidArgumentException('kit.home_name must be a route name (letters, digits, dots, dashes and underscores), '.get_debug_type($name).' given.');
        }

        return $name;
    }

    /** The home as a breadcrumb parent: the feature whose route options name it, and the route name. */
    public static function parent(): array
    {
        return ['session-ui', self::name()];
    }
}
