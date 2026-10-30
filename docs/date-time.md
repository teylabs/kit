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

- **Timezone.** It comes from session-ui's `kitTimezone` share: the browser's `tz` cookie when it's a real zone, then the person's saved zone, then the workspace's, then UTC. Without the share (a page outside session-ui), it's the browser's own zone, then UTC. `useTimezone()` returns it.
- **Locale.** It's the page's `<html lang>`, then the browser's.
- **Overrides.** The `timezone` and `locale` props override both, for example to show a value in a workspace's zone. `now` fixes the clock, for tests.

## Without a component

`formatDateTime(value, format, { timezone, locale, now, relativeUntilDays })` and `fullDateTime(value, …)` return the same strings, for table cells, toasts and titles. `isCalendarDate(value)` tells the two kinds of value apart.

## Coverage

- `tests/js/date-time.test.mjs`:
  - every format;
  - a calendar date in the zones furthest apart and across DST changes (it never shifts);
  - an instant on either side of the date line;
  - the relative thresholds;
  - ambiguous input shown as given.
- `browser/run.mjs`: a real render (`<time>`, `datetime`, `title`, the live ticker) and axe.
- `tests/InertiaSharesCheck.php`: the `kitTimezone` share follows the `tz` cookie and survives a flush.
