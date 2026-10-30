# Adoption inventory

`resources/adoption/surfaces.json` is the kit's adoption inventory: one row per surface the kit offers, with what counts as using it (`uses`) and what an app's own version of it looks like (`handRolled`). tey/standards' `standards adoption` reads it against an app and reports each row as `adopted`, `hand-rolled`, `missing` or `n/a`.

## Keeping it complete

Two checks keep the inventory honest:

- `composer test:adoption-fixtures` runs `standards adoption` on each small app under `tests/fixtures/adoption` and compares the row it gets with `expected.json`.
- `composer test:adoption-completeness` makes sure nothing the kit installs is invisible to the inventory:
  - **Every new page, layout or shell component needs a surfaces row or an `internal.json` entry.** Each `.vue` file a feature installs under `resources/js/pages` must be named by some row's `uses.files`, `uses.components` or `uses.imports` (a component or import name counts only for a named partial, not a `Page.vue` or `Layout.vue`), or be listed under `files` in `resources/adoption/internal.json` with a one-line reason (kit-internal flows such as the welcome steps, or sample features).
  - **Every row with `handRolled` signals needs a fixture.** Some case in `expected.json` must expect that row `hand-rolled`. Add a minimal fixture app that hand-rolls the surface, using the row's own signals as the guide. A row whose signal genuinely can't be fixtured goes under `unproven` in `internal.json` with a reason.
  - An `internal.json` entry that no longer matches (the file is gone, a row names it now, or a fixture proves the row now) fails as stale. Rows that no fixture expects `adopted` are listed as a note.
