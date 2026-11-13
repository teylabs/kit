# kitImports: per-file `@kit/ui` imports in dev

`@kit/ui` is one barrel that names every kit component. Vite's dev server serves an app's own files one module at a time and cannot pre-bundle them, so a page that imports `Button` from the barrel makes the browser fetch every file the barrel names. On the starter's dashboard that was 216 of 320 requests.

`kitImports()` is a Vite plugin that ships with the shared components at `vendor/tey/kit/resources/js/components/Kit/kitImports.js` (with its types in `kitImports.d.ts`; before 0.18 `core` copied it to `resources/js/components/Kit/`). In dev it rewrites

```ts
import { Button, Page as KitPage } from '@kit/ui';
```

into one import per file:

```ts
import Button from '/vendor/tey/kit/resources/js/components/Kit/Form/Button.vue';
import KitPage from '/vendor/tey/kit/resources/js/components/Kit/Layout/Page.vue';
```

App code doesn't change, and the barrel stays the one place that names the UI, for types and for apps that replace it.

- **Dev only.** It sets `apply: 'serve'`. A production build already tree-shakes the barrel, so `vite build` output stays byte-identical with the plugin on or off.
- **It reads the barrel `@kit/ui` resolves to.** That can be the kit's `index.ts` or your own module. It maps each `export { … } from '…'` name to its file, and follows `export * from '…'` into the modules it re-exports (`export * from '@/components/Kit'`). A name your module declares or exports itself stays in the barrel and wins over one a star brings in.
- **It never drops a name.** Anything it can't place stays on an `import { … } from '@kit/ui'` and loads the barrel as before. That covers `import * as`, a default import, an unknown name and a star into `node_modules`. Type-only imports were already erased by TypeScript.
- **It runs after the other transforms** (`enforce: 'post'`), so it sees plain JavaScript from `.vue` and `.ts` files alike, and it parses with Vite's own parser.
- Kit components import each other by relative path, never through the barrel (`tests/js/kit-imports.test.mjs` fails if one does), so the rewrite reaches the whole graph.
- Option: `kitImports({ specifier: '@kit/ui' })`, the barrel's import specifier.

## Measured

Starter dashboard, cold dev server, signed in:

| | Requests | Kit files | Transferred |
| --- | --- | --- | --- |
| Before | 320 | 216 | 22.07 MB |
| With `kitImports()` | 149 | 62 | 15.56 MB |

The kit's browser check (`browser/run.mjs`, "kitImports") serves the dashboard grid in dev over the real barrel. It renders, never requests `index.ts`, and loads 44 kit files instead of 216. Every kit page it serves keeps no import through the barrel.

## Migrating an app

1. Get the plugin's files, `resources/js/components/Kit/kitImports.js` and `kitImports.d.ts`. Since 0.18 the kit writes no files into an app: an app that lacks them copies core's stubs (`vendor/tey/kit/resources/features/core/components/Kit/kitImports.js.stub` and `kitImports.d.ts.stub`, without the `.stub`).
2. In `vite.config.ts`, import the plugin and add it after `vue()`:

   ```ts
   import { kitImports } from './vendor/tey/kit/resources/js/components/Kit/kitImports.js';

   plugins: [/* … */ vue(/* … */), kitImports()],
   ```

   If your config points `@kit/ui` at your own module, nothing else changes: the plugin follows that alias.
3. Check: `npm run build` (output unchanged), `npx vue-tsc --noEmit`, then `npm run dev`. Load a page and confirm in the Network panel that `components/Kit/index.ts` is no longer requested.

From 0.18 the components load from the package: see [kit-components.md](kit-components.md) for the aliases and the import path above.

If your app imports from the barrel by path (`@/components/Kit` or `@/components/Kit/index`), switch those imports to `@kit/ui`. The plugin only rewrites the `@kit/ui` specifier.
