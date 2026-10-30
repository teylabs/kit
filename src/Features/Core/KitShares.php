<?php

namespace Tey\Kit\Features\Core;

use Closure;
use Inertia\Inertia;

/**
 * The kit's Inertia props, one producer each: core's `kitUi` and `kitToasts`, session-ui's `environment`, `kitPassword`, `passkeyOffer` and `shell`
 * (built by the PageShell). Each provider hands its closures over at boot; they are shared then, and again first in every web request
 * (StartKitRequest, and once more late in PrepareKitRequest), so a flush of Inertia's shared props (pest-plugin-browser flushes before each request, as an Octane-style reset or a host
 * might) can't leave a page without them. Each stays a closure, evaluated when a page renders, so nothing resolves early and no value outlives its
 * request.
 *
 * The host wins: a key is shared only while Inertia's factory doesn't already hold it. At boot, a key a provider shared earlier stays; on a request,
 * StartKitRequest runs first in `web`, so a key the host shared on the factory before the request is kept, a host middleware later in `web` can read
 * (and wrap) the kit's prop, and the host's own HandleInertiaRequests merges over it; the late restore in PrepareKitRequest again fills only missing
 * keys. Re-sharing the kit's own unchanged closures each request is a no-op. Without a flush, the
 * kit's boot closures are already there and the host's middleware merges over them, as before. Nothing here flushes.
 */
final class KitShares
{
    /** @param  array<string, Closure>  $props */
    private function __construct(private array $props = []) {}

    /** @param  array<string, Closure>  $props */
    public static function provide(array $props): void
    {
        $shares = self::instance();
        $shares->props = [...$shares->props, ...$props];
        self::shareMissing($props);
    }

    /** Put back any of the kit's props a flush removed, leaving every key already shared (the host's, or the kit's own) as it is. */
    public static function restore(): void
    {
        self::shareMissing(self::instance()->props);
    }

    /** The application's own set, so two apps in one process (a test run) never share each other's props. */
    private static function instance(): self
    {
        $app = app();
        if (! $app->bound(self::class)) {
            $app->instance(self::class, new self);
        }

        return $app->make(self::class);
    }

    /** @param  array<string, Closure>  $props */
    private static function shareMissing(array $props): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }
        $missing = array_diff_key($props, Inertia::getShared());
        if ($missing !== []) {
            Inertia::share($missing);
        }
    }
}
