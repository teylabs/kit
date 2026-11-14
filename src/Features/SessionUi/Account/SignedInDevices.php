<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Session\CookieSessionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Concierge\Support\UserSessions;
use Tey\Kit\Features\Core\Kit;
use Throwable;

/**
 * A person's signed-in devices, on every session driver: one `signed_in_devices` row per signed-in session, written once at the end of the sign-in
 * request (keyed by the session's final id) and touched at most every `kit.devices.touch_minutes` after that. The session itself carries what the
 * middleware compares (`kit.device`: the row's id, the user, the session id the row knows and when it was last touched), so a normal page runs no
 * query against the table; it reads one revocation marker from the cache store instead (TrackSignedInDevice).
 *
 * Signing a device out deletes its row first, which is the authority (that session fails its next touch), then marks its session revoked in the
 * cache store, so its next request is cut off, then destroys the session through the configured handler. The row holds the session id encrypted
 * (decrypted only to destroy it) and its HMAC with the app key (to find it); the IP only as its /24 or /48, the user agent only as its browser and
 * platform, and a country only from Cloudflare's CF-IPCountry when the host names it. Every write is conditioned on the row's id, its user and the
 * session's current HMAC, so a request carrying an old session id never moves a row back, and a session whose row is gone is never given a new one.
 */
final class SignedInDevices
{
    public const string TABLE = 'signed_in_devices';

    /** The session key the middleware reads: ['id', 'user', 'sid', 'at'] once tracked, ['pending' => true, 'remembered' => bool] after a sign-in. */
    public const string KEY = 'kit.device';

    /** The one header a country is read from, when `kit.devices.country_header` names it: Cloudflare's, set by its proxy, never by the browser. */
    public const string COUNTRY_HEADER = 'CF-IPCountry';

    /**
     * The sign-in and sign-out ends, on the web guard. Login can't key the row: Fortify regenerates the session id after it (PrepareAuthenticatedSession), so
     * it only leaves `pending` for the middleware, which writes the row once the request ends. Another sign-in over a session that has a row ends that row.
     * Logout (and CurrentDeviceLogout) deletes this session's row. Neither runs a query on an ordinary sign-in.
     */
    public static function listen(Dispatcher $events): void
    {
        $events->listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'web' || ($session = self::session()) === null) {
                return;
            }
            if (self::tracked($device = $session->get(self::KEY))) {
                self::forget($device);
            }
            $session->put(self::KEY, ['pending' => true, 'remembered' => (bool) $event->remember]);
        });
        $events->listen([Logout::class, CurrentDeviceLogout::class], function (Logout|CurrentDeviceLogout $event): void {
            if ($event->guard !== 'web' || ($session = self::session()) === null) {
                return;
            }
            if (self::tracked($device = $session->get(self::KEY))) {
                self::forget($device, $event->user?->getAuthIdentifier());
            }
            $session->forget(self::KEY);
        });
    }

    /**
     * Whether a session's `kit.device` names its row.
     *
     * @phpstan-assert-if-true array{id: int, user: int|string, sid: string, at: int} $device
     */
    public static function tracked(mixed $device): bool
    {
        return is_array($device) && ! isset($device['pending']) && isset($device['id'], $device['user'], $device['sid'], $device['at']);
    }

    /** N: a device's last-seen time is written at most this often. */
    public static function touchMinutes(): int
    {
        return (int) config('kit.devices.touch_minutes', 5);
    }

    /** Refuses a `kit.devices` block the kit can't honour, so a typo never quietly turns a check off. Run at boot. */
    public static function validate(): void
    {
        $devices = config('kit.devices', []);
        if (! is_array($devices) || array_diff(array_keys($devices), ['touch_minutes', 'country_header', 'store']) !== []) {
            throw new RuntimeException('kit.devices takes touch_minutes, country_header and store only.');
        }
        $minutes = $devices['touch_minutes'] ?? 5;
        if (! is_int($minutes) || $minutes < 1) {
            throw new RuntimeException('kit.devices.touch_minutes must be a whole number of minutes, at least 1.');
        }
        if (($devices['country_header'] ?? null) !== null && $devices['country_header'] !== self::COUNTRY_HEADER) {
            throw new RuntimeException('kit.devices.country_header is null or '.self::COUNTRY_HEADER.' (Cloudflare\'s, which its proxy sets): the kit reads a country from no other header.');
        }
        if (($devices['store'] ?? null) !== null && ! is_string($devices['store'])) {
            throw new RuntimeException('kit.devices.store names a cache store, or is null for the default one.');
        }
    }

    /** Rows last seen before this belong to sessions that have ended: the session lifetime plus N. They are no longer listed, and are pruned. */
    public static function staleBefore(): CarbonInterface
    {
        return now()->subMinutes((int) config('session.lifetime', 120) + self::touchMinutes());
    }

    /** The HMAC-SHA256 of a session id with the app key: how a row is found. Never sent to the page. */
    public static function hash(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    /**
     * The session id's HMAC with the app key and with each of APP_PREVIOUS_KEYS, so a key rotation finds the row (and the next touch rewrites it with
     * the current key).
     *
     * @return list<string>
     */
    public static function hashes(string $sessionId): array
    {
        $keys = array_values(array_filter([(string) config('app.key'), ...(array) config('app.previous_keys', [])], fn ($key): bool => is_string($key) && $key !== ''));

        return array_values(array_unique(array_map(fn (string $key): string => hash_hmac('sha256', $sessionId, $key), $keys)));
    }

    /** The /24 of an IPv4 address (an IPv4-mapped IPv6 one too) or the /48 of an IPv6 one: "203.0.113.0/24", "2001:db8:abcd::/48". The full address is never kept. */
    public static function ipPrefix(?string $ip): ?string
    {
        $packed = $ip === null ? false : @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        }

        return strlen($packed) === 4
            ? inet_ntop(substr($packed, 0, 3)."\0").'/24'
            : inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10)).'/48';
    }

    /** The two-letter country Cloudflare names, only when the host turned `kit.devices.country_header` on, and only a code (`XX`, unknown, is none). */
    public static function country(Request $request): ?string
    {
        if (config('kit.devices.country_header') !== self::COUNTRY_HEADER) {
            return null;
        }
        $code = (string) $request->headers->get(self::COUNTRY_HEADER, '');

        return preg_match('/\A[A-Z]{2}\z/', $code) === 1 && $code !== 'XX' ? $code : null;
    }

    /**
     * The row of a session that has none yet (it signed in during this request, or it is from before 0.22): inserted, or found when a concurrent first
     * request of the same session got there first (the unique HMAC). Null when no row of this user holds the session: the caller signs it out.
     */
    public static function track(Request $request, string $sessionId, int|string $userId, bool $remembered): ?int
    {
        DB::table(self::TABLE)->insertOrIgnore(self::row($sessionId, $userId, $request->userAgent(), $request->ip(), self::country($request), $remembered, now()));
        $id = DB::table(self::TABLE)->where('session_hash', self::hash($sessionId))->where('user_id', $userId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * kit:devices:backfill's row for a session from the database session table, signed in before 0.22: its user agent and IP as the table holds them, cut
     * to the browser, platform and prefix, and its last activity as both times. False when the session already has a row (a second run adds nothing).
     */
    public static function adopt(string $sessionId, int|string $userId, ?string $agent, ?string $ip, CarbonInterface $lastActive): bool
    {
        return DB::table(self::TABLE)->insertOrIgnore(self::row($sessionId, $userId, $agent, $ip, null, false, $lastActive)) > 0;
    }

    /**
     * Moves last_seen_at forward for this device's row, still held by this session, and rewrites its HMAC with the current key. False when the row is
     * gone or holds another session: the caller signs the request out.
     *
     * @param  array{id: int, user: int|string, sid: string, at: int}  $device
     */
    public static function touch(array $device, string $sessionId): bool
    {
        $now = now();
        $updated = self::held($device, $sessionId)->where('last_seen_at', '<', $now)->update(['last_seen_at' => $now, 'session_hash' => self::hash($sessionId)]);

        // Nothing updated: the row is gone, held by another session, or already touched this second by a concurrent request (last_seen_at only goes forward).
        return $updated > 0 || self::held($device, $sessionId)->exists();
    }

    /** @param  array{id: int, user: int|string, sid: string, at: int}  $device */
    public static function exists(array $device, string $sessionId): bool
    {
        return self::held($device, $sessionId)->exists();
    }

    /**
     * Compare-and-swap after a regenerate: the row moves to the new session id only while it still holds the old one, and the old id is marked revoked,
     * so a request still carrying it is cut off rather than moving the row back. False when the row is gone or moved: the caller signs out.
     *
     * @param  array{id: int, user: int|string, sid: string, at: int}  $device
     */
    public static function move(array $device, string $to): bool
    {
        $moved = self::held($device, $device['sid'])->update(['session_hash' => self::hash($to), 'session_id' => Crypt::encryptString($to)]) > 0;
        if ($moved) {
            self::mark(self::hash($device['sid']));
        }

        return $moved;
    }

    /**
     * A sign-out on this device (Logout): its row goes.
     *
     * @param  array{id: int, user: int|string, sid: string, at: int}  $device
     */
    public static function forget(array $device, int|string|null $userId = null): void
    {
        self::held(['user' => $userId ?? $device['user']] + $device, $device['sid'])->delete();
    }

    /**
     * Whether this session id was revoked (its device signed out elsewhere, or the id retired by a regenerate): one read of the cache store. Null when
     * the store can't answer (reported, scrubbed): the caller checks the row in the database instead, so the request is never let through unchecked.
     */
    public static function revoked(string $sessionId): ?bool
    {
        try {
            return self::store()->has(self::marker(self::hash($sessionId)));
        } catch (Throwable $e) {
            report(new RuntimeException('Signed-in devices could not read the cache store ('.$e::class.'), so the request was checked against signed_in_devices instead.'));

            return null;
        }
    }

    /**
     * Signs one of the person's other devices out (DELETE /me/security/devices/{id}). `missing`: no row of theirs by that id (someone else's or one already
     * gone, alike). `current`: this request's own session, which the Sign out on this device ends instead. `ended`: the row is gone, the session marked
     * revoked and destroyed; `at_once` is false only when neither the marker nor the handler could reach the store, so the device's next touch ends it.
     * `$confirm` (the password asked again) runs once the device is found to be another of theirs, before anything changes; it throws to refuse.
     *
     * @param  ?Closure(): void  $confirm
     * @return array{status: 'missing'|'current'|'ended', agent?: string, remembered?: bool, at_once?: bool}
     */
    public static function signOut(Authenticatable $user, int $deviceId, Request $request, ?Closure $confirm = null): array
    {
        $row = DB::table(self::TABLE)->where('id', $deviceId)->where('user_id', $user->getAuthIdentifier())->first();
        if ($row === null) {
            return ['status' => 'missing'];
        }
        if (in_array($row->session_hash, self::hashes($request->session()->getId()), true)) {
            return ['status' => 'current'];
        }
        if ($confirm !== null) {
            $confirm();
        }
        // The row first: it is the authority, so even with the store down that session fails its next touch. Gone meanwhile (another tab): already signed out.
        if (DB::table(self::TABLE)->where('id', $row->id)->where('user_id', $user->getAuthIdentifier())->where('session_hash', $row->session_hash)->delete() === 0) {
            return ['status' => 'missing'];
        }
        $atOnce = self::end($row);
        $remembered = (bool) $row->remembered;
        // Laravel keeps one remember-me token per person: a remembered device can only be kept from signing back in by cycling it, which ends remember-me on
        // the person's other devices too (they stay signed in, and sign in again next time).
        if ($remembered && $user->getRememberTokenName()) {
            UserSessions::cycleRememberToken($user);
            if (method_exists($user, 'save')) {
                $user->save();
            }
        }
        $agent = SecurityLog::describeDevice($row->browser, $row->platform);
        SecurityLog::record($user->getAuthIdentifier(), 'device_signed_out', $request, ['agent' => $agent, 'ip' => $row->ip_prefix]);

        return ['status' => 'ended', 'agent' => $agent, 'remembered' => $remembered, 'at_once' => $atOnce];
    }

    /**
     * Ends every session of the person but $except (none for an account deletion): each row deleted, its session marked revoked and destroyed through the
     * handler. On the database driver the sessions table is swept too, for sessions from before tracking. A missing table while session-ui is on throws
     * (#967). Returns how many live sessions ended.
     */
    public static function endAll(Authenticatable $user, ?string $except = null): int
    {
        $ended = 0;
        if (Kit::present('session-ui', self::TABLE)) {
            $keep = $except === null ? [] : self::hashes($except);
            $live = self::staleBefore();
            foreach (DB::table(self::TABLE)->where('user_id', $user->getAuthIdentifier())->orderBy('id')->get() as $row) {
                if (in_array($row->session_hash, $keep, true) || DB::table(self::TABLE)->where('id', $row->id)->where('session_hash', $row->session_hash)->delete() === 0) {
                    continue;
                }
                self::end($row);
                $ended += (int) Carbon::parse($row->last_seen_at)->greaterThan($live);
            }
        }
        if (config('session.driver') === 'database') {
            $ended += DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())->when($except !== null, fn ($query) => $query->where('id', '!=', $except))->delete();
        }

        return $ended;
    }

    /**
     * The rows of a person last seen within the session lifetime plus N, most recent first (50).
     *
     * @return list<object>
     */
    public static function live(int|string $userId): array
    {
        return DB::table(self::TABLE)->where('user_id', $userId)->where('last_seen_at', '>', self::staleBefore())->orderByDesc('last_seen_at')->orderByDesc('id')->limit(50)->get()->all();
    }

    /** @return array<string, mixed> a new row: the session id encrypted and its HMAC, the browser and platform alone, the IP's prefix alone */
    private static function row(string $sessionId, int|string $userId, ?string $agent, ?string $ip, ?string $country, bool $remembered, CarbonInterface $at): array
    {
        [$browser, $platform] = SecurityLog::agentParts($agent);

        return [
            'user_id' => $userId,
            'session_hash' => self::hash($sessionId),
            'session_id' => Crypt::encryptString($sessionId),
            'browser' => $browser,
            'platform' => $platform,
            'ip_prefix' => self::ipPrefix($ip),
            'country' => $country,
            'remembered' => $remembered,
            'created_at' => $at,
            'last_seen_at' => $at,
        ];
    }

    /**
     * A deleted row's session: marked revoked (by its id's HMAC with the current key, which the middleware reads), then destroyed through the handler.
     * An id that won't decrypt is reported, scrubbed, and its stored HMAC marked instead. True when either reached its store, so it ends on its next request.
     */
    private static function end(object $row): bool
    {
        try {
            $id = Crypt::decryptString((string) $row->session_id);
        } catch (Throwable $e) {
            report(new RuntimeException('A signed-in device\'s session id could not be decrypted ('.$e::class.'); its row is gone, so it is signed out at its next request.'));
            $id = null;
        }
        $marked = self::mark($id === null ? (string) $row->session_hash : self::hash($id));

        return ($id !== null && self::destroy($id)) || $marked;
    }

    /**
     * Destroys a session through the configured handler. A cookie session can't be reached (CookieSessionHandler would queue a cookie on this response), so it
     * is skipped: its marker and its next touch end it. A failure (the store down) is reported scrubbed: the class only, never the id.
     */
    private static function destroy(string $sessionId): bool
    {
        try {
            $handler = app('session')->driver()->getHandler();
            if ($handler instanceof CookieSessionHandler) {
                return false;
            }
            $handler->destroy($sessionId);

            return true;
        } catch (Throwable $e) {
            report(new RuntimeException('A signed-in device\'s session could not be destroyed ('.$e::class.'); its row is gone, so it is signed out at its next request.'));

            return false;
        }
    }

    /** Marks a session HMAC revoked in the cache store for the session lifetime plus N, after which the session has expired anyway. */
    private static function mark(string $hash): bool
    {
        try {
            return self::store()->put(self::marker($hash), true, now()->addMinutes((int) config('session.lifetime', 120) + self::touchMinutes()));
        } catch (Throwable $e) {
            report(new RuntimeException('A signed-in device could not be marked revoked in the cache store ('.$e::class.'); its row is gone, so it is signed out at its next touch.'));

            return false;
        }
    }

    private static function session(): ?Session
    {
        $request = app()->bound('request') ? app('request') : null;

        return $request instanceof Request && $request->hasSession() ? $request->session() : null;
    }

    private static function marker(string $hash): string
    {
        return 'kit.devices.revoked.'.$hash;
    }

    private static function store(): CacheRepository
    {
        return Cache::store(config('kit.devices.store'));
    }

    /**
     * The device's row while it still holds this session: its id, its user and the session's HMAC (with any key), never one of them alone.
     *
     * @param  array{id: int, user: int|string, sid?: string}  $device
     */
    private static function held(array $device, string $sessionId): Builder
    {
        return DB::table(self::TABLE)->where('id', $device['id'])->where('user_id', $device['user'])->whereIn('session_hash', self::hashes($sessionId));
    }
}
