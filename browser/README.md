# Browser job

`run.mjs` runs the kit's real-browser checks: Playwright (Chromium) over a Vite build of the core component stubs, with axe-core for accessibility.

```sh
cd browser
npm ci
npx playwright install chromium
node --test run.mjs
```

The pages are in `src/`, picked by query string (`?workbench`, `?workbench-menu`); the kit's stubs are copied into `.kit` and built against `kit.css`'s default tokens. `@/components/ui/button` is the host's real shadcn-vue Button (`src/button`, with cva), so a variant paints its real classes (`primary` is `bg-primary`); the other shadcn pieces are stand-ins in `src/ui.js`.

## Contrast checks need a non-modal surface

Run axe's `color-contrast` on a non-modal surface. A modal menu or dialog sets `aria-hidden` on the rest of the page, and axe then skips that content, so a
contrast failure can pass unseen (a modal menu reports `aria-hidden-focus` and the landmark rules instead). The Workbench row menu is non-modal, so its open-menu
run checks the destructive Delete item's contrast on the popover.
