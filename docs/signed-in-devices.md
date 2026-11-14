# Signed-in devices

`/me/security` lists the devices a person is signed in on, and signs one out, or every other one at once. It works on every session driver: database, Redis and Valkey, Memcached, DynamoDB, file and cookie. Only the `array` driver, which is for tests, lists none.

## How it works

Each signed-in session has one row in `signed_in_devices`, an infrastructure table the kit owns. It loads from the package, so `php artisan migrate` creates it. It has no adopt step and no `kit.models` entry.

| Column | Holds |
| --- | --- |
| `user_id` | The person. The foreign key cascades, so the rows go with the account. Null once the device is signed out (the row is retired). |
| `session_hash` | The HMAC-SHA256 of the session id with the app key. Unique; never sent to the page. |
| `session_id` | The session id, encrypted with the app key. It is decrypted only to destroy that session. Null once retired. |
| `browser`, `platform` | Parsed from the user agent ("Chrome", "macOS"). The user agent itself is not stored. |
| `ip_prefix` | The IPv4 /24 or IPv6 /48. The full address is not stored. |
| `country` | A two-letter code, only from Cloudflare's `CF-IPCountry` and only when `kit.devices.country_header` names it. |
| `remembered` | Whether the session signed in with remember-me. |
| `created_at`, `last_seen_at` | The sign-in, and the last request (accurate to `touch_minutes`). |

The table has no workspace column. Nobody but the person sees their devices, and no workspace, operator or admin path reaches them.

`TrackSignedInDevice` joins the `web` group after the session starts. The row is written once, at the end of the sign-in request, keyed by the session's final id (Fortify regenerates it after `Login`). After that:

- **Every signed-in request** reads one key from the cache store, to see whether the session was revoked. It never queries the database on an ordinary page, and never loads the user.
- **Every `touch_minutes`** (5 by default) one request updates `last_seen_at`. It checks that the row still holds this session for this person. If the row is gone, the session is signed out.
- **A regenerate** (an invitation, the demo sign-in) moves the row to the new id by compare-and-swap. A request still carrying the old id is signed out.
- **Sign out** retires the row and marks the session revoked. A request still in flight that writes the session back afterwards is signed out on its next request. If the mark is lost, the next touch signs it out. It is never given a new row.
- **A session with no row yet** (a sign-in, or a session from before 0.23) gets one only while its person's user row exists. A session left over from a deleted account is signed out on its next request.

A guest's request reads nothing.

## Signing a device out

`DELETE /me/security/devices/{id}` asks for the password, like the page's other actions, and is throttled to 10 a minute. The row is retired first, so even with the session store down, that device is signed out at its next touch. A retired row keeps only the session id's HMAC (no person, session id, device or network) until that session would have expired. Its unique HMAC refuses the session a new row, so even a session from before 0.23 can't come back if its revocation mark is lost. Then:

- its session id is marked revoked in the cache store, so its next request is signed out;
- its session is destroyed through the configured handler (a cookie session can't be reached, so it is skipped);
- a remembered device also cycles the remember-me token. Laravel keeps one per person, so the person's other devices will ask them to sign in again next time. The page says so;
- the security log records `device_signed_out` with that device's browser, platform and network, and the person is mailed a SecurityNotice (`kit.security_mail`). The mail never names the device.

The row and the remember-me token change in one transaction. The mark and the destroy run only after it commits, and both are idempotent.

Another person's device id, a device already signed out and a non-numeric id are each a 404. The current device is a 422: signing it out is the app's own Sign out.

"Sign out other sessions", a password change, account deletion (immediate or deferred) and the kit's anonymiser end every other device the same way. On the database driver they also delete the person's rows from the `sessions` table, for sessions from before 0.23.

Each runs in one transaction with the change it belongs to: the new password, the cycled remember-me token, or the deletion. The sessions are marked revoked and destroyed only after it commits. With session-ui on, a missing `signed_in_devices` or `user_state` table throws, and the whole change rolls back: the password, the token and every session stay as they were (#967).

Giving a session a row and ending a person's sessions each lock that person's user row first, so they never interleave. A sign-in or a backfill that runs during "Sign out other sessions" either finishes first, and its row is retired, or waits and then sees that the other sessions were ended.

## Configuration

```php
// config/kit.php
'devices' => ['touch_minutes' => 5, 'country_header' => null, 'store' => null],
```

- `store` is the cache store that holds the revocation marks: `null` for the default store. Name one every server shares (Redis, say). Name one that isn't the `database` cache store, or each signed-in page runs one query on the `cache` table. With a per-server store such as `array` or `file`, a device signed out on one server is cut off at its next touch, within `touch_minutes`.
- `touch_minutes` is a whole number, at least 1.
- `country_header` is `null` or `CF-IPCountry`. Turn it on only behind Cloudflare, whose proxy sets the header.

Any other key or value stops the boot. A `store` that is empty or names no store in `cache.stores` stops the boot in `local` and `testing`. Anywhere else it is reported, and the default store holds the marks instead, so the check is never turned off.

If the cache store can't be read, the request checks the row in the database instead, so it is never let through unchecked. The failure is reported scrubbed (its class only).

## Keeping the table short

A session can expire in its store without telling anyone. The page lists only rows seen within `session.lifetime` plus `touch_minutes`, and `model:prune` deletes the older ones once you schedule it:

```php
use Tey\Kit\Features\SessionUi\Models\SignedInDevice;

Schedule::command('model:prune', ['--model' => [SignedInDevice::class]])->daily();
```

## Upgrading from 0.22

- Run `php artisan migrate`.
- On the database session driver, run `php artisan kit:devices:backfill` once. It adds a row for each signed-in session in the `sessions` table, and a second run adds nothing. It adds none for a person whose other sessions were all ended since the upgrade. Other drivers can't list a person's sessions; each of their devices gets its row on its next request.
- A session from before the upgrade gets its row on its next request, until every other session of the person is ended ("Sign out other sessions", a password change or an account deletion). Those can't reach a session that has no row yet, so from then on such a session keeps only a row that still holds it (a backfilled one) and is otherwise signed out on its next request. The mark is kept in `user_state`, not the cache store.
- Take the new Security page for the per-device Sign out: `php artisan kit:scaffold workspace-ui --diff --only=resources/js/pages/Workspace/Account/Security`. Until then the old page lists every device on every driver, and its "Sign out other sessions" works everywhere.

## Checks

`composer test:signed-in-devices` runs a synthetic app (SQLite files, and PostgreSQL 17 in CI) through the router with real Fortify and Concierge. It covers sign-in, zero queries, regenerate, per-driver sign-out, already-gone sessions, ownership, the resurrection race, concurrency, ending everywhere, remember-me, adoption and backfill, retention, key rotation and Octane. `test:migrations`, `test:lifecycle-pages`, `test:security-mail`, `test:account-form-payloads` and the browser job cover the rest.

Not covered: a real Redis, Valkey, Memcached or DynamoDB server (only the shared `CacheBasedSessionHandler` class is exercised), MySQL and SQL Server, file sessions on several servers, a consumer app, and browsers other than Chromium.
