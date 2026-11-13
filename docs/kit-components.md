# Shared components from the package

Since 0.18 the kit's shared components (`resources/js/components/Kit`: the `@kit/ui` barrel, the form set, layout, tables, overlays and the rest) are not copied into the app. They ship in the package at `vendor/tey/kit/resources/js/components/Kit`, and the app resolves them through two aliases. `composer update tey/kit` updates them. Pages and partials are different: `kit:scaffold` writes those once and the app owns them.

Pages import kit components as before, through `@kit/ui` or by path (`@/components/Kit/Form/Button.vue`), so neither pages nor the components themselves change. The components still import your shadcn-vue primitives from `@/components/ui` (core's `requires.host_ui`), so keep those.

## Vite

In `vite.config.ts`, point `@/components/Kit` and `@kit/ui` at the package:

```ts
const kitComponents = decodeURIComponent(new URL('./vendor/tey/kit/resources/js/components/Kit', import.meta.url).pathname);

export default defineConfig({
    resolve: {
        alias: {
            '@/components/Kit': kitComponents,
            '@kit/ui': `${kitComponents}/index.ts`,
            // '@kit/shell' stays on your scaffolded resources/js/pages/Workspace/Partials/index.ts.
        },
    },
    plugins: [/* … */ vue(/* … */), kitImports()],
});
```

`laravel-vite-plugin` adds `@` → `resources/js` after your own aliases, so `@/components/Kit` wins over it. If you write the aliases as an array, list `@/components/Kit` before any `@` entry of your own.

`kitImports()` ([kit-imports.md](kit-imports.md)) comes from the package too:

```ts
import { kitImports } from './vendor/tey/kit/resources/js/components/Kit/kitImports.js';
```

If `server.warmup.clientFiles` lists `./resources/js/components/Kit/index.ts`, change it to `./vendor/tey/kit/resources/js/components/Kit/index.ts`.

## TypeScript

In `tsconfig.json`, `compilerOptions.paths`:

```json
"@/components/Kit": ["./vendor/tey/kit/resources/js/components/Kit/index.ts"],
"@/components/Kit/*": ["./vendor/tey/kit/resources/js/components/Kit/*"],
"@kit/ui": ["./vendor/tey/kit/resources/js/components/Kit/index.ts"]
```

TypeScript takes the longest matching pattern, so these win over `@/*`. vue-tsc checks the components a page imports.

## Tailwind

Tailwind skips git-ignored folders such as `vendor/`, so name the components in `resources/css/app.css`, next to your other `@source` lines:

```css
@source '../../vendor/tey/kit/resources/js/components/Kit';
```

Without it, every class used only by a kit component is missing from the build. On the starter that halved `app.css`.

## Checks

- `npm run build`: the output is the same as with the copied folder (the starter's was byte-identical).
- `npx vue-tsc --noEmit`.
- `npm run dev`: with `kitImports()`, a page's `@kit/ui` import becomes imports of files under `/vendor/tey/kit/resources/js/components/Kit/`.

A Composer path repository symlinks `vendor/tey/kit` to a folder outside the app. Vite follows the link, then looks for the components' `vue` and `reka-ui` imports next to that folder and finds no `node_modules` (`Failed to resolve import source "reka-ui"`). Set `resolve.preserveSymlinks: true` in that app, or install `tey/kit` from the registry.
