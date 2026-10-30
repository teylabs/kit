<?php

namespace Tey\Kit\Features\SessionUi;

use InvalidArgumentException;
use LogicException;

/**
 * Per-group switches over session-ui's routes (and workspace-ui's, which keep session-ui's options): `kit.routes.session-ui.<group> => false` leaves that group's
 * routes and names out, every group on by default. Transitional: a host with its own invitation flow turns `invitations` off until it moves onto the kit's.
 * Fail closed: an unknown key or a non-boolean value is refused at boot, and the kit's own links to a group that is off are guarded with on().
 */
final class SessionUiRoutes
{
    /** The groups: the invitation link (show, accept, the account it creates), the welcome steps and welcome card, the passkey offer, the first-run demo's sign-in. */
    public const array GROUPS = ['invitations', 'welcome', 'passkey-offer', 'demo-login'];

    /** The feature-wide options every `kit.routes.<feature>` takes (FeaturesServiceProvider::routeOptions). */
    private const array OPTIONS = ['enabled', 'prefix', 'middleware', 'name'];

    /** Refuses an unknown key or a non-boolean group switch under `kit.routes.session-ui`, naming the groups and options it accepts. */
    public static function validate(): void
    {
        foreach ((array) config('kit.routes.session-ui', []) as $key => $value) {
            if (in_array($key, self::OPTIONS, true)) {
                continue;
            }
            if (! in_array($key, self::GROUPS, true)) {
                throw new InvalidArgumentException('Unknown key kit.routes.session-ui.'.$key.': the route groups are '.implode(', ', self::GROUPS).' and the options are '.implode(', ', self::OPTIONS).'.');
            }
            if (! is_bool($value)) {
                throw new InvalidArgumentException('kit.routes.session-ui.'.$key.' must be true or false, '.get_debug_type($value).' given.');
            }
        }
    }

    /** Whether `$group`'s routes are registered: the routes as a whole are on and the group is not switched off. */
    public static function on(string $group): bool
    {
        if (! in_array($group, self::GROUPS, true)) {
            throw new LogicException('Unknown session-ui route group: '.$group);
        }
        self::validate();

        return config('kit.routes.session-ui.enabled', true) !== false && config('kit.routes.session-ui.'.$group, true) !== false;
    }
}
