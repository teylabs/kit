<?php

namespace Tey\Kit\Features\Core;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * The clock times of day show in: `12h`, `24h`, or `auto`, the locale's own. Resolution: the person's own choice (session-ui keeps it in user_state as `clock`,
 * read through Registry::userClock), then the workspace's when the app registers one (Registry::workspaceClockUsing, read inside the tenant), then
 * `kit.clock.default` (`auto` unless the app sets it). The browser gets the same answer as the `kitClock` share. time() and range() format a server-built
 * string (a mail, a receipt) with it, in the Carbon's own zone and the app's locale.
 */
final class Clock
{
    /** `12h` or `24h`, else null: anything else (`auto` included) is no choice. */
    public static function valid(mixed $clock): ?string
    {
        return $clock === '12h' || $clock === '24h' ? $clock : null;
    }

    /**
     * `12h`, `24h` or `auto` for a person: a user or their id, or, when null, the signed-in person (none in a queued job or for a guest). A failure reading
     * the person's or the workspace's choice falls back rather than failing the page or the mail.
     */
    public static function resolve(Authenticatable|int|null $user = null): string
    {
        $registry = app(Registry::class);
        $id = $user instanceof Authenticatable ? (int) $user->getAuthIdentifier() : ($user ?? self::signedIn());
        $own = $workspace = null;
        try {
            $own = $id === null || $registry->userClock() === null ? null : ($registry->userClock())($id);
        } catch (Throwable) {
            $own = null;
        }
        try {
            $workspace = $registry->workspaceClock() === null ? null : ($registry->workspaceClock()['get'])();
        } catch (Throwable) {
            $workspace = null;
        }

        return self::valid($own) ?? self::valid($workspace) ?? self::valid(config('kit.clock.default')) ?? 'auto';
    }

    /** A time of day: `3:30 PM` (12h), `15:30` (24h), or the locale's own (auto). */
    public static function time(CarbonInterface $at, Authenticatable|int|null $user = null): string
    {
        return self::format($at, self::resolve($user));
    }

    /** A span of times of day, `9:00 AM – 5:00 PM`, on one clock. */
    public static function range(CarbonInterface $start, CarbonInterface $end, Authenticatable|int|null $user = null): string
    {
        $clock = self::resolve($user);

        return self::format($start, $clock).' – '.self::format($end, $clock);
    }

    private static function format(CarbonInterface $at, string $clock): string
    {
        $at = $at->copy()->locale(app()->getLocale());

        return $at->isoFormat(match ($clock) {
            '12h' => 'h:mm A',
            '24h' => 'HH:mm',
            default => 'LT',
        });
    }

    private static function signedIn(): ?int
    {
        try {
            $id = app()->bound('auth') ? app('auth')->guard('web')->id() : null;
        } catch (Throwable) {
            $id = null;
        }

        return $id === null ? null : (int) $id;
    }
}
