# Browser checks

`node --test browser/run.mjs` (after `npm ci` and `npx playwright install chromium` in `browser/`) builds a page from the kit's core stubs with Vite and drives it in Chromium with Playwright. It checks the kit Modal's overlays, and the Workbench with zero items, with row menus, and reordering over a stand-in for Inertia v3's HTTP client (string bodies, a server that refuses a stale version), including axe-core runs. `?form-attrs` gives each kit form control a caller's `aria-describedby`, `invalid` and `required`, and reads them off the focusable control. CI runs it as "Kit components in a browser (Playwright)".

## Running axe

- **Check contrast on non-modal surfaces.** A modal (a modal dialog, or a menu opened with `modal`) sets `aria-hidden` on the rest of the page, and axe skips hidden content, so a contrast failure behind or inside it goes unreported. Open menus and popovers non-modally (`:modal="false"`) for a `color-contrast` run, as the Workbench row-menu test does.
- **Disable a rule only for a stated reason, in the test.** `region` is off for open menus because kit menus are portalled to `<body>`, outside the page's landmarks.
