# Browser checks

`node --test browser/run.mjs` (after `npm ci` and `npx playwright install chromium` in `browser/`) builds a page from the kit's core stubs with Vite and drives it in Chromium with Playwright. It checks the kit Modal's overlays, and the Workbench with zero items, with row menus, and reordering over a stand-in for Inertia v3's HTTP client (string bodies, a server that refuses a stale version), including axe-core runs. `?form-attrs` gives each kit form control a caller's `aria-describedby`, `invalid` and `required`, and reads them off the focusable control. `?form-submit` is a FormCard over useSubmitForm with a fake socket handler, and `?workbench-form=submit` or `=inertia` a Workbench with `:form` over a useSubmitForm or a stand-in useForm with `save`. session-ui's guest pages are built separately (`auth.html`, `vite.auth.config.js`) on the real `@inertiajs/vue3` and shadcn-vue's Input, so `<Form>`'s props, visit options, resets and Inertia's own error modal behave as in an app; the tests answer each submit with Playwright's `page.route`. CI runs it as "Kit components in a browser (Playwright)".

## Shadcn pieces

`@/components/ui/button` is the host's real shadcn-vue Button (`src/button`, with cva), so a variant paints its real classes (`primary` is `bg-primary`); the other shadcn pieces are stand-ins in `src/ui.js`.

## Running axe

- **Check contrast on non-modal surfaces.** A modal (a modal dialog, or a menu opened with `modal`) sets `aria-hidden` on the rest of the page, and axe skips hidden content, so a contrast failure behind or inside it goes unreported. Open menus and popovers non-modally (`:modal="false"`) for a `color-contrast` run, as the Workbench row-menu test does.
- **Disable a rule only for a stated reason, in the test.** `region` is off for open menus because kit menus are portalled to `<body>`, outside the page's landmarks.
