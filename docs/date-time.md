# Dates and times: DateTime

`DateTime` (from `@kit/ui`) shows a date or time to the viewer. It renders a `<time>` element: the machine value goes in `datetime` and the full date (and, for an instant, the time with its zone) goes in `title`. It formats with the browser's `Intl` only, with no date library.

```vue
<DateTime :value="order.created_at" format="relative" live />
<DateTime :value="order.due_on" format="date" />
```

## Two kinds of value

| Value | Example | Shown |
| --- | --- | --- |
| A **calendar date** | `2026-11-01` | As that day, everywhere. It is never converted between zones, so it never shifts across midnight or a DST change. |
| An **instant**, RFC 3339 with its offset | `2026-10-29T20:30:00-03:00`, `2026-10-29T23:30:00Z` | In the viewer's timezone. |

An instant without an offset (`2026-10-29T20:30:00`) is ambiguous, so it isn't guessed: it's shown exactly as given. Send instants from the server with their offset (Laravel's `toIso8601String()`).

## Formats

| `format` | A calendar date | An instant |
| --- | --- | --- |
| `local` (the default), `datetime` | Nov 1, 2026 | Oct 29, 2026, 8:30 PM |
| `date` | Nov 1, 2026 | Oct 29, 2026 |
| `time` | Nov 1, 2026 (a date has no time) | 8:30 PM |
| `ymd` | 2026-11-01 | 2026-10-29, in the viewer's zone |
| `relative` | "yesterday", "in 3 days" | "3 minutes ago", "in 2 hours" |

Past `relative-until-days` (7 by default), a relative value shows the absolute date (and time, for an instant). With `live`, a relative time re-renders each minute. Every live DateTime on the page shares one timer, which runs only while one is mounted.

## The viewer's timezone and locale

- **Timezone.** It comes from session-ui's `kitTimezone` share: the browser's `tz` cookie when it's a real zone, then the person's saved zone (Account › Profile › Date & time, or the welcome step), then the workspace's (Settings › General › Date & time), then UTC. Without the share (a page outside session-ui), it's the browser's own zone, then UTC. `useTimezone()` returns it.
- **Locale.** It's the page's `<html lang>`, then the browser's.
- **Overrides.** The `timezone` and `locale` props override both, for example to show a value in a workspace's zone. `now` fixes the clock, for tests.

## The viewer's clock: 12- or 24-hour

A time of day shows on the viewer's clock: `12h` (3:30 PM), `24h` (15:30), or `auto`, the locale's own (en-US 3:30 PM, en-GB 15:30). It's resolved on the server, in this order:

1. **The person's own choice.** Account › Profile › Date & time › Time format: *Use workspace setting*, *12-hour* or *24-hour*. Kept in `user_state` as `clock` (`12h` or `24h`); *Use workspace setting* deletes the row.
2. **The workspace's**, when the app registers one with `Registry::workspaceClockUsing()` (below): Settings › General › Date & time › Time format.
3. **`kit.clock.default`** (env `KIT_CLOCK`): `12h`, `24h` or `auto`.
4. **`auto`**.

Anything other than `12h` or `24h` at any step counts as no choice, and a workspace getter that throws falls back to the next step. Within a web request the result is remembered per person and workspace, so a list that formats many times and the `kitClock` share read the person's choice and call the workspace getter once; `ClientClock::save()` and the workspace setter forget it, and `Clock::forget()` does for an app that changes its own setting another way. Outside a request (a job, a command) every call reads afresh. The default is `auto`, so an app that sets nothing renders exactly as before.

Core shares the preference with every Inertia page as `kitClock` (always `12h`, `24h` or `auto`): signed-in, guest, public and portal pages alike, also on a host without session-ui (there it's the workspace's and the default). `useClock()` returns it (`auto` without the share), and the browser resolves `auto` by the page's locale. `DateTime`, `formatDateTime()`, `fullDateTime()`, `InputTime`, `InputTimeRange` and `checkedAtLabel()` pass it to `Intl` as `hourCycle`: `h12` for 12h, `h23` for 24h (so midnight is 12:30 AM or 00:30, never 0:30 or 24:30), and nothing for auto. An explicit option still wins: `<DateTime clock="12h">`, `formatDateTime(value, 'time', { clock: '24h' })`, `<InputTime :hour-cycle="12">`. A time input always submits a 24-hour `HH:MM`, whatever it shows.

### Where it's set: Date & time

The clock is a regional preference, so it sits beside the time zone, never under Appearance. Both pages group the two in a section headed **Date & time**, time zone first:

| Page | Time zone | Time format |
| --- | --- | --- |
| **Account › Profile** (workspace-ui), the person's own | The person's zone, saved and confirmed (the welcome step doesn't ask again) | *Use workspace setting*, *12-hour*, *24-hour* |
| **Settings › General** (settings), the workspace's | The workspace's zone | *12-hour*, *24-hour*, *Automatic*; shown only when the app wires `workspaceClockUsing()` |

Profile's Date & time is one form, saved with **Save date & time** as `PUT /me/profile/clock` (`timezone`, a zone identifier, optional; `clock`: `workspace`, `12h` or `24h`). General saves the time format with the rest of its form (`clock`: `auto`, `12h` or `24h`). A save without `clock` leaves the workspace's clock alone; a `clock` sent while no app keeps one is refused on the field.

### The workspace's clock

The kit has no workspace setting of its own for the clock: the app keeps it where it keeps its other workspace settings and registers how it's read and written, the same way the settings feature registers the timezone. Once it does, Settings › General shows *Time format* under Date & time, reads it through the getter and saves it through the setter. Both run inside the workspace's tenant context. The getter returns `12h`, `24h` or null (no choice, shown as *Automatic*); the setter gets the acting person's id and `12h`, `24h` or null (*Automatic*):

```php
use Tey\Kit\Features\Core\Registry;

// In a service provider's boot(), with the app's own settings class (a `clock` property, null by default).
app(Registry::class)->workspaceClockUsing(
    fn (): ?string => AppWorkspaceSettings::current()->clock,
    function (int $actorId, ?string $clock): void {
        $settings = AppWorkspaceSettings::current();
        $settings->clock = in_array($clock, ['12h', '24h'], true) ? $clock : null;
        $settings->save();
    },
);
```

The setter runs inside General's save, in the same transaction as the name and time zone. Register one only: a second call replaces the first. Without a registration General shows only *Time zone* under Date & time.

### Server-built times

`Tey\Kit\Features\Core\Clock` resolves the clock and formats times for mail, receipts, digests and chat messages. It never needs a request, so a queued job passes the person and workspace it was made for:

```php
use Tey\Kit\Features\Core\Clock;

Clock::current();                       // '12h' or '24h': the signed-in person in the current workspace
Clock::for($member, $workspace);        // '12h' or '24h' for a user (or id) and a workspace (model or id), either null
Clock::preference($member, $workspace); // '12h', '24h' or 'auto', before the locale resolves it

Clock::time($booking->starts_at->setTimezone($zone), $member);   // "3:30 PM" or "15:30"
Clock::range($shift->starts_at, $shift->ends_at, $member, $workspace);
// "Tue 2 Jun, 10:00 to 12:00" or "Tue 2 Jun, 10:00 AM to 12:00 PM"; across midnight "Tue 2 Jun, 22:00 to Wed 3 Jun, 02:00"
```

- **`auto` resolves through the app's locale** (`app()->getLocale()`): en, en_CA and ko are 12-hour; en_GB, de and fr are 24-hour (Carbon's own time format for the locale). A mailable's `->locale()`, or a notifiable's `preferredLocale()`, sets the app locale while it renders, so it decides too.
- **A given workspace** is read inside it, through `Registry::inWorkspace()`: the workspaces feature registers `Workspaces::run()` there, so the app's getter sees that workspace as current (with row-level security as `run()` sets it). A null workspace is the current one, if any. Without the workspaces feature the getter runs as it is.
- **`time()` and `range()`** with no person and no workspace use `current()`; otherwise `for()` them. Each formats in the Carbon's own zone (a range's end in the start's zone) and the app's locale, keeping the locale's own pattern when its clock is the one asked for (ko on auto: 오후 3:30). A range names the date once, and both days when it crosses midnight.

## Without a component

`formatDateTime(value, format, { timezone, locale, now, relativeUntilDays, clock })` and `fullDateTime(value, …)` return the same strings, for table cells, toasts and titles. `isCalendarDate(value)` tells the two kinds of value apart. `hourCycle(clock)` returns the `Intl` option for a clock (`{}` for auto), to spread into your own formatter's options.

## Coverage

- `tests/js/date-time.test.mjs`:
  - every format;
  - a calendar date in the zones furthest apart and across DST changes (it never shifts);
  - an instant on either side of the date line;
  - the relative thresholds;
  - ambiguous input shown as given.
- `browser/run.mjs`: a real render (`<time>`, `datetime`, `title`, the live ticker) and axe.
- `tests/InertiaSharesCheck.php`: the `kitTimezone` share follows the `tz` cookie and survives a flush.
- The clock:
  - `tests/js/date-time.test.mjs` and `tests/js/mail-sending.test.mjs`: 24h in a 12-hour locale, 12h in a 24-hour one, midnight on each, and `auto` byte for byte the same as no clock;
  - `browser/run.mjs` (`?clock`): DateTime and the time inputs on each clock, an explicit clock winning, `auto` and no share unchanged; Profile's and General's Date & time: the heading, Time zone before Time format, General's Time format only when wired, each saved and reloaded;
  - `tests/InertiaSharesCheck.php`: the resolution order of `kitClock` and its fallbacks; `kitClock` on a public page of a host without session-ui and on a portal page; `Clock::for()`, `current()`, `time()` and `range()`, a given workspace read through the runner, and a queued mail rendered by the queue;
  - `tests/InertiaSharesCheck.php`: 30 `Clock::time()` calls and the share on one page make one `user_state` read and one getter call, and a save or the setter is seen by the next call in the same request;
  - `tests/WorkspaceRunCheck.php`: `Registry::inWorkspace()` is `Workspaces::run()`, and `Clock::for(null, $workspace)` reads that workspace's clock;
  - `tests/AccountFormPayloadsCheck.php`: the recorded Profile Date & time payload saved through the real route (time zone and clock), a refused clock and a refused zone, and *Use workspace setting* deleting the row;
  - `tests/FormPayloadsCheck.php`: General's recorded time format refused without `workspaceClockUsing`, then saved through its setter (inside the workspace, as the admin), *Automatic* clearing it, a bad value refused, and a save without one leaving it alone.
