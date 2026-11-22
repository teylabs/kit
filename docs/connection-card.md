# ConnectionCard

One external connection (an accounting, payments or agent provider) on a settings page. Props: `title`, `description`, `status` (`connected`, `disconnected`, `checking`, `error`), `account`, `connectedAt`, `lastCheckedAt`, `error`, `actions` (connect or disconnect descriptors) and `level`.

- The status shows as a Badge with its text (Connected, Not connected, Checking…, Failed), never colour alone.
- `disconnected` with no facts shows "Not connected yet."
- `checking` sets `aria-busy` and holds the actions busy.
- `error` is shown as an alert.
- The default slot holds extra settings; a FormCard for credentials stays a sibling section.

It draws its own card. It replaces the hand-rolled bordered status row on pages such as Square and Accounting settings.

Tailwind and the theme tokens only. Browser specimen: `?section-shapes` (`&dark`), with `KIT_SECTION_SHAPES_SCREENSHOT=<path prefix>` saving each scheme at 1440 and 390px.
