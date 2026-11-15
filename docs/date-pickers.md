# Date and time pickers

Seven form controls (from `@kit/ui`) ask for dates and times. Each one submits plain ISO strings through hidden inputs, so a form posts them like any other field. All seven are built on reka-ui (`@internationalized/date` is a declared npm requirement of `core`).

| Control | Submits | Picks with |
| --- | --- | --- |
| `InputDate` | `name` = `2026-11-01` | Segments you can type, plus a calendar |
| `InputCalendarDate` | `name` (default `id`) = `2026-11-01`, or `''` once cleared | InputDate's segments and calendar, with "Not set" while empty and Clear ([below](#a-date-that-may-be-empty-inputcalendardate)) |
| `InputDateRange` | `name[start]`, `name[end]` = dates | Segments, plus one calendar (two clicks) |
| `InputMonth` | `name` = `2026-11` | A 3×4 grid of the year's months |
| `InputYear` | `name` = `2026` | A 3×4 grid of twelve years |
| `InputTime` | `name` = `09:30` (24-hour) | Segments; the locale decides 12- or 24-hour display |
| `InputTimeRange` | `name[start]`, `name[end]` = times | Segments |

```vue
<InputDate name="due_on" label="Due" :default-value="task.due_on" />
<InputDateRange name="stay" label="Stay" :default-value="{ start: booking.arrives_on, end: booking.leaves_on }" />
<InputTime name="opens_at" label="Opens at" default-value="09:00" :step="15" />
```

Every control takes `name`, `label`, `id`, `defaultValue`, `min`, `max`, `description`, `error`, `disabled` and `required`, and emits `canonical` with its value (InputCalendarDate requires `id` and defaults `name` to it). Inside Inertia's `<Form>`, the error is `errors[name]`; for a range it is also `errors[name.start]` or `errors[name.end]`. Inside a locked `LockedForm` the control is read-only and its calendar or grid doesn't open, but the hidden value is still submitted.

## A date that may be empty: InputCalendarDate

`InputCalendarDate` is `InputDate` for a date that may be left blank. While it has no date it says so under the field (`emptyLabel`, "Not set"): an empty segment group reads as a value to some people, and Safari's native date input even shows today. While it has a date it offers a Clear button (`clearLabel`, "Clear", named "Clear <label>" for a screen reader), unless it is `required`, `disabled` or in a locked `LockedForm`, where emptying it is not allowed.

```vue
<InputCalendarDate id="listed-on" name="listed_on" label="Listed on" :default-value="listing.listed_on" :span="3" />
<InputCalendarDate id="due" v-model="form.due_on" label="Due" empty-label="No due date" />
```

- **Clear** submits an empty string (the hidden input's value is `''`), emits `update:modelValue` and `canonical` with `''`, fires an `input` event on the form so it turns dirty, and moves focus to the field's first segment.
- **Props.** `id` and `label` are required; `name` defaults to `id`. `modelValue` (`v-model`, the date or `''`) or `defaultValue` (a `YYYY-MM-DD`, `''` or null) starts it; a longer ISO value is cut to its date. `span` is a FormCard's columns, as on `Field`. `min`, `max`, `description`, `error`, `disabled` and `required` are InputDate's.
- **A form reset** puts the starting value back: `defaultValue`, or with `v-model` the value it mounted with, emitted through `update:modelValue`.
- Other attributes land on the field's segment group, as on `InputDate`.

## Dates never shift

A date, a month, a year and a time of day are **wall values**. A picker never converts one between zones: a due date of `2026-11-01` is 1 November for every viewer, on every server, across midnight and DST changes. The viewer's zone (`kitTimezone`, the same share `DateTime` reads) decides only which day is "today" in the calendar.

A **timed value** (an instant) is different. It names a moment, so it goes through the viewer's zone, and its string carries the offset:

```ts
import { toInstant, fromInstant } from '@kit/ui';

toInstant('2026-11-01', '09:30', 'America/Halifax'); // '2026-11-01T09:30:00-03:00'
fromInstant('2026-11-01T12:30:00Z', 'America/Halifax'); // { date: '2026-11-01', time: '09:30' }
```

Pair an `InputDate` with an `InputTime` and join them with `toInstant` when you need a moment. A wall time that a DST change skips moves forward by the gap; a repeated wall time takes the earlier moment. A value without an offset is not an instant, and `fromInstant` returns blanks for it.

## Keyboard

The calendars follow the APG date picker dialog. The arrow keys move a day or a week, PageUp and PageDown move a month, and Home and End go to the ends of the week. Enter chooses a date, and Escape closes the calendar and returns focus to its button. The month and year grids follow the APG grid. Arrows move one cell, and moving off an edge turns the year (or the twelve years) and keeps the column. Home and End go to the ends of the row, Control+Home and Control+End to the first and last cell, and PageUp and PageDown turn a page and keep the cell. In a date or time field, each segment (day, month, hour and so on) takes typing and the up and down arrows.

On a phone (narrower than 640px), the calendar and the grids open as a bottom sheet, full width at the bottom edge. `css/kit.css` holds the rule.

## Ranges

A range submits both ends. Leaving both blank is an empty range. Filling only one end, or putting the end before the start, shows an error on the field and blocks the browser's native submit. It doesn't block a submit made in script, so check ranges on the server too.

## On the server

`Tey\Kit\Validation\IsoDate` and `IsoRange` accept exactly what the pickers submit:

```php
use Tey\Kit\Validation\IsoDate;
use Tey\Kit\Validation\IsoRange;

$request->validate([
    'due_on' => ['required', IsoDate::date(min: '2026-01-01')],
    'period' => ['required', IsoDate::month()],
    'opens_at' => ['required', IsoDate::time('06:00', '22:00')],
    'starts_at' => ['required', IsoDate::instant()],
    'stay' => ['required', IsoRange::dates()],
    'hours' => [IsoRange::times()],
]);
```

The bounds are inclusive and written in the same form as the value. A wall value is checked exactly as written, so the server's zone never changes the result. `IsoDate::instant()` refuses a value without an offset, so a moment can't silently take the server's zone. `IsoRange` fails on the range's own attribute when one end is blank or the ends are reversed. Add `required` if an empty range should fail too.

## Coverage

- **Node:** `tests/js/date-input.test.mjs` covers the values, the zone and DST rules, the range checks and the grid keys.
- **Browser:** `browser/run.mjs` drives the keys, a date in two far-apart zones, the phone sheet, the locked state and axe in Chromium.
- **InputCalendarDate (browser, `?field-paths`):** "Not set" while empty; Clear while filled, absent when required, disabled or locked; Clear's empty submitted value, `v-model`, the form's input event and the focus; a date chosen from the calendar; a form reset; axe with contrast, light and dark.
- **Server:** `tests/IsoDateCheck.php` runs the rules through Laravel's validator.
- **Not covered:** no test drives these controls inside a host's Inertia `<Form>`, and there is no screen-reader run.
