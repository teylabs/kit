<?php

namespace Tey\Kit\Features\Core;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * The clock times of day show in. A preference is `12h`, `24h` or `auto`: the person's own choice (session-ui keeps it in user_state as `clock`, read
 * through Registry::userClock), then the workspace's when the app registers one (Registry::workspaceClockUsing, read inside that workspace), then
 * `kit.clock.default` (`auto` unless the app sets it). `auto` is the locale's own clock, so for() and current() answer `12h` or `24h`, resolving it
 * through the app's locale. Pages get the preference as the `kitClock` share (the browser resolves `auto` by the page's locale). time() and range()
 * format server-built strings (a mail, a receipt, a digest, a chat message) on the resolved clock, in each Carbon's own zone and the app's locale.
 * Nothing here needs a request: for() takes the person and the workspace, so a queued job passes the ones it was made for.
 *
 * Within a web request a preference is resolved once per person and workspace (RequestMemo, scoped, so nothing outlives the request under Octane): a
 * list that formats 30 times, and the `kitClock` share beside it, read the person's choice and call the workspace getter once. ClientClock::save()
 * and the workspace setter forget it; an app that changes its own setting another way calls forget().
 */
final class Clock
{
    /** `12h` or `24h`, else null: anything else (`auto` included) is no choice. */
    public static function valid(mixed $clock): ?string
    {
        return $clock === '12h' || $clock === '24h' ? $clock : null;
    }

    /** `12h` or `24h` for the signed-in person in the current workspace (or, for a guest or a job, the current workspace's and the default). */
    public static function current(): string
    {
        return self::for(self::signedIn());
    }

    /**
     * `12h` or `24h` for a person (a user or their id) and a workspace (a model or its id), either of them null; no request is read. A null workspace
     * is the current one, when the app has a current workspace at all. `auto` is resolved through the app's locale (en 12h, en_GB 24h).
     */
    public static function for(Authenticatable|int|null $user = null, object|int|null $workspace = null): string
    {
        return self::valid(self::preference($user, $workspace)) ?? self::localeClock((string) app()->getLocale());
    }

    /**
     * The preference, `12h`, `24h` or `auto`, for a person and a workspace, as for(). A failure reading the person's or the workspace's choice falls back
     * rather than failing the page or the mail.
     */
    public static function preference(Authenticatable|int|null $user = null, object|int|null $workspace = null): string
    {
        $id = $user instanceof Authenticatable ? (int) $user->getAuthIdentifier() : $user;

        return RequestMemo::instance()->get('clock:'.($id ?? '-').':'.self::workspaceKey($workspace), fn (): string => self::resolve($id, $workspace));
    }

    /** Forgets the preferences resolved in this request, so the next read sees a changed choice. */
    public static function forget(): void
    {
        if (app()->resolved(RequestMemo::class)) {
            RequestMemo::instance()->flush();
        }
    }

    private static function resolve(?int $id, object|int|null $workspace): string
    {
        $registry = app(Registry::class);
        $own = $setting = null;
        try {
            $own = $id === null || $registry->userClock() === null ? null : ($registry->userClock())($id);
        } catch (Throwable) {
            $own = null;
        }
        try {
            $get = $registry->workspaceClock()['get'] ?? null;
            $setting = $get === null ? null : ($workspace === null ? $get() : $registry->inWorkspace($workspace, fn (): mixed => $get()));
        } catch (Throwable) {
            $setting = null;
        }

        return self::valid($own) ?? self::valid($setting) ?? self::valid(config('kit.clock.default')) ?? 'auto';
    }

    /** The `kitClock` share: the signed-in person's preference (`auto` left for the browser to resolve by the page's locale). */
    public static function shared(): string
    {
        return self::preference(self::signedIn());
    }

    /** A locale's own clock, from its time format (Carbon's `LT`): `12h` when the hour is 12-hour (en, en_CA, ko), else `24h` (en_GB, de, fr). */
    public static function localeClock(string $locale): string
    {
        $format = (string) (CarbonImmutable::now()->locale($locale)->getIsoFormats()['LT'] ?? 'h:mm A');

        return str_contains((string) preg_replace('/\[[^\]]*\]/', '', $format), 'h') ? '12h' : '24h';
    }

    /** A time of day: `3:30 PM` or `15:30`. With no person and no workspace, the current() clock; else for() them. */
    public static function time(CarbonInterface $at, Authenticatable|int|null $user = null, object|int|null $workspace = null): string
    {
        return self::format($at, self::clockFor($user, $workspace));
    }

    /**
     * A span of time sharing its date: `Tue 2 Jun, 10:00 to 12:00` or `Tue 2 Jun, 10:00 AM to 12:00 PM`. When the ends fall on different days, each
     * has its own (`Tue 2 Jun, 22:00 to Wed 3 Jun, 02:00`). The end is shown in the start's zone. The person and workspace are as for time().
     */
    public static function range(CarbonInterface $start, CarbonInterface $end, Authenticatable|int|null $user = null, object|int|null $workspace = null): string
    {
        $clock = self::clockFor($user, $workspace);
        $end = $end->copy()->setTimezone($start->getTimezone());
        $start = $start->copy()->locale(app()->getLocale());
        $end = $end->locale(app()->getLocale());
        $tail = $start->isSameDay($end) ? self::format($end, $clock) : $end->isoFormat('ddd D MMM').', '.self::format($end, $clock);

        return $start->isoFormat('ddd D MMM').', '.self::format($start, $clock).' to '.$tail;
    }

    private static function clockFor(Authenticatable|int|null $user, object|int|null $workspace): string
    {
        return $user === null && $workspace === null ? self::current() : self::for($user, $workspace);
    }

    /** The locale's own time format when its clock is the one asked for (so `ko` keeps `오후 3:30`), else a plain 12- or 24-hour one. */
    private static function format(CarbonInterface $at, string $clock): string
    {
        $locale = (string) app()->getLocale();
        $pattern = self::localeClock($locale) === $clock ? 'LT' : ($clock === '12h' ? 'h:mm A' : 'HH:mm');

        return $at->copy()->locale($locale)->isoFormat($pattern);
    }

    /** The workspace a preference is for: the one given, else the current tenant (spatie's container binding), else none. */
    private static function workspaceKey(object|int|null $workspace): string
    {
        if ($workspace === null) {
            $binding = config('multitenancy.current_tenant_container_key', 'currentTenant');
            $workspace = is_string($binding) && app()->bound($binding) ? app($binding) : null;
        }
        if (is_object($workspace)) {
            $workspace = method_exists($workspace, 'getKey') ? $workspace->getKey() : ($workspace->id ?? spl_object_id($workspace));
        }

        return $workspace === null ? '-' : (string) $workspace;
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
