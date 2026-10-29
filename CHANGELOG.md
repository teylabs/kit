# Changelog

## Unreleased

### Added

- **`TolerantImage`**, a raster generator for PNGs with malformed metadata ([docs/media.md](docs/media.md#conversions)): before GD loads a PNG, it removes the `iCCP`, `iTXt`, `zTXt` and `tEXt` chunks from a temporary copy (the original is untouched). Where the host's libpng treats a bad colour profile as an error, the conversion then succeeds. A corrupt PNG (a bad critical chunk, a truncated file) is passed on unchanged and still fails loudly. It replaces `Image::class` in `media-library.image_generators`; `kit.media.tolerant_png` false opts out.
- [docs/media.md](docs/media.md#where-failed-jobs-live): where failed jobs live. On Laravel Cloud's managed queue they're in the queue's own store, not the `failed_jobs` table.

### Known limitations

- **TolerantImage's "fails without" half isn't proven in CI.** CI's GD builds print libpng's warnings to stderr instead of raising them in PHP, so under Laravel's error handler the malformed-iCCP PNG can't fail there and the check prints SKIP. That half is proven only on GD builds that raise the warning in PHP: verified locally on PHP 8.4 and 8.5, and by the client's own repro. CI proves the conversion succeeds.

## v0.5.13

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:88a4470f…0348` → `sha256:31138a46…dc77`): DataTable, RowActions, ActionSegment, ActionMenuItems, `actionPlacement.ts`, `index.ts` and `contract.ts` change; new `Feedback/Badge.vue` and `Table/rowLabel.ts`.
- **A `get` action descriptor (without a `confirm`) is now a link** in RowActions, ActionBar and the ⌄ menus, not a button. A test or stylesheet that looked for a `<button>` there needs updating.

### Added

- **`<Badge>`** ([docs/data-table.md](docs/data-table.md#status-cells-badge)): a status label with tones neutral, info, success, warning and danger on the kit tone tokens (AA in light and dark), and sizes sm and md.
- **DataTable `row-label`** (a column key or a function): row controls read "Actions for Ana Lopez" and "Select Ana Lopez", falling back to the row number.
- **`get` descriptors render as links** in RowActions (a kit Button `href` inline; the ⌄ item is the link), ActionBar segments and its menu rows (`rendersAsLink()`): an Inertia Link, or an `<a target="_blank" rel="noopener noreferrer">` with `newTab`. Non-GET, confirm and dialog actions stay buttons.
- [docs/data-table.md](docs/data-table.md): TableQuery, row labels, links, Badge, and that several lists on one page means tabs.

### Fixed

- **TableData's generated TypeScript is precise:** a row is `{ id: string | number; actions?: Action[] } & Record<string, unknown>` (not `Record<string, any>`), and `emptyState` is `'first-use' | 'no-results' | null` (not `unknown | null`).
- **A GET action that asks a confirmation now asks before it navigates.** ActionBar segments rendered any GET descriptor as a bare Link, so a GET with a `confirm` navigated without asking. Such an action is now a button that opens the confirmation and visits once confirmed, in RowActions, ActionBar and the ⌄ menus alike.

## v0.5.12

### Fixed

- **PerformConversions fails loudly instead of finishing with nothing made** (found in production: 2 of 407 images).
  - An original it can't read (Media Library writes 0 bytes and returns on a refused read when the disk doesn't throw) throws `media.conversions.unreadable_original`.
  - An image that ends with none of its requested conversions throws `media.conversions.none_generated`.
  - Both are retried. A media row deleted before the job runs still drops the job, now with a `media.conversions.missing_model` warning naming the media id.

## v0.5.11

### Fixed

- **TableQuery is generic over its model** (`@template TModel of Model`): `for(Builder<TModel>)`, `search`/`filter` closures over `Builder<TModel>`, `build(Closure(TModel))`, and the fluent methods return `$this`. So a typed caller passes PHPStan for any model.
- **`TableQuery::defaultSort($key)` sorts by the key's declared SQL column.** It passed the key itself to spatie, so a key that differs from its column (`joinedAt` → `created_at`) sorted by a column that doesn't exist.
- **A page past the last redirects to the last page**, keeping the rest of the query and the session's flashed data (a toast). Before, it showed an empty page.

## v0.5.10

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:046a13b2…10c0` → `sha256:88a4470f…0348`): `css/kit.css` changes.

### Fixed

- **Toasts meet WCAG AA.** The Toaster used Sonner's own rich colours, and its success toast failed AA (4.26:1, axe color-contrast). kit.css now maps every Sonner rich-colour variable (`--success-*`, `--info-*`, `--warning-*` and `--error-*` for bg, border and text) onto new kit tone tokens (`--kit-tone-<tone>-bg|border|fg`), light and dark. The text contrast is now at least 6.8:1 (light) and 10.3:1 (dark). A host theme overrides the tokens. `toaster-contrast.test.mjs` checks the mapping and the ratios.

## v0.5.9

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ff8ac070…63ae` → `sha256:046a13b2…10c0`): Workbench and `workbenchLayout.ts` change.

### Added

- **Dense Workbench surfaces** ([docs/workbench.md](docs/workbench.md#dense-surfaces-default-collapsed-panes-no-headings-an-overlay-navigator)):
  - `:default-collapsed="{ navigator: true }"` collapses panes the first time a layout shows, only without a remembered layout (a saved one wins).
  - `:headings="false"` drops the region headings; the landmarks keep their names, and the pane toggles move to the actions bar.
  - `navigator-mode="overlay"` opens the navigator as a sheet over the stage (a kit Modal: focus trap, Esc, focus back), so the stage never resizes; `close-on-select` closes it on a choice.

## v0.5.8

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:9223364e…f59e` → `sha256:ff8ac070…63ae`): RadioCardGroup and `contract.ts` change; new `Form/radioCommit.ts`.

### Added

- **RadioCardGroup `commit="click"`** ([docs/radio-card.md](docs/radio-card.md#commit-on-click-commitclick)) for live polls: the arrow keys only move focus (a roving tabindex over `role="radio"` buttons), and Space, Enter, a click or a tap commits, setting `v-model` and emitting `commit` once. Re-selecting the current option emits nothing, and the choice is announced politely. This is a documented deviation from the APG's auto-select. The default (`change`) is unchanged.

## v0.5.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4585ea42…da76` → `sha256:9223364e…f59e`): Dialog, ActionBar, RowActions, BulkBar, `actionPlacement.ts`, `index.ts` and `contract.ts` change; new `Overlay/confirmText.ts`.
- **ActionBar now runs a descriptor itself when the host does not listen to `@action`**: its `confirm` first, then an Inertia visit. Before, such an action did nothing. A host that listens is unchanged.

### Added

- **Typed confirmation** ([docs/typed-confirmation.md](docs/typed-confirmation.md)) for destructive actions:
  - Dialog `confirm-text` (with `case-sensitive` and `confirm-text-label`): a labelled input, and Confirm disabled until the text matches, announced politely; paste allowed. The typed text is emitted with `confirm` and sent as `confirmation` with the `action` visit.
  - A descriptor's `confirm.typed` (`TypedConfirmation::confirm()`) is rendered by RowActions, BulkBar and ActionBar, which send `confirmation`.
  - The server re-checks with `TypedConfirmation::require($request, $text)`; a missing or mismatched `confirmation` is a validation error.
  - `composer test:typed-confirmation` and `typed-confirmation.test.mjs` cover it.

## v0.5.6

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a2bf2341…fcb2` → `sha256:4585ea42…da76`): Workbench, `workbench.ts`, `useWorkbench.ts`, `workbenchStage.ts`, FloatingToolbar and `index.ts` change.

### Added

- **Workbench element attribute:** the host names the attribute its stage elements carry their id in, with `<Workbench element-attribute="data-block-id">` or `useWorkbench(key, { elementSelector })` (a name or `[name]`). The stage toolbar's selection rects (`selectedRects`, which now takes the attribute), FloatingToolbar `mode="stage"` and `reposition()` use it. The default stays `data-workbench-element`. `elementAttributeName()` and `DEFAULT_ELEMENT_ATTRIBUTE` are exported, and an invalid name falls back to the default.
- **Workbench `:keys="false"`** for live sessions and transports (the app owns the keyboard): none of the Workbench's bindings fire (↑/↓, j/k, Alt+↑/↓, `/`, F6, Esc, ⌘[ / ⌘], zoom). Focus management and the region landmarks stay. `enabledCommand()` is the pure gate.

## v0.5.5

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f1e58788…5cf7` → `sha256:a2bf2341…fcb2`): new `Form/RadioCardGroup.vue`, `Form/EmojiTray.vue` and `Form/emojiTray.ts`; Button, Field, `index.ts` and `contract.ts` change.
- Field's label now has the id `<id>-label`. A host test that selected the label by an exact attribute list may need updating.

### Added

- **Media delivery** ([docs/media.md](docs/media.md#delivery)): `MediaDelivery::respond($request, $resolve, $conversion, $public, $mode)` serves an item behind the app's own route, and the app's resolver decides what's a 404. `proxy` streams the bytes: public items are immutable with an ETag and a 304, private ones are `private, no-store`, everything is nosniff, and anything that isn't an image downloads under a sandbox CSP. `redirect` answers a 302 to a signed bucket URL, and the redirect's own max-age is at most half the TTL (`kit.media.redirect_ttl`). `kit.media.delivery` sets the default mode. `composer test:media-delivery` covers it.
- **Adopting existing objects** ([docs/media.md](docs/media.md#adopting-existing-objects)): `AdoptObject` makes an object already on a disk a Media item over its key (via `KeyPathGenerator`), probed by size and its first bytes, with no copy and idempotent. `AdoptMediaCommand` is the command pattern: an app subclass names the owners and each one's key. It's tenant-aware through spatie's `TenantAware` command trait (every tenant, or `--tenant=`) and refuses to run with no tenant. It runs in id chunks (`--chunk`), one `TenantAware` queued job per chunk on `kit.media.queue` that fails loudly with no tenant (`--sync` runs them inline). `--dry-run` prints per-tenant counts only, and a tenant whose source has rows but no owners is a failing warning. Re-runs are no-ops. `ConfirmUpload`'s header read is now `ObjectProbe`. `composer test:media-adopt` covers it.
- **`<RadioCardGroup>`** ([docs/radio-card.md](docs/radio-card.md)): large tappable choices (a poll's options) as native radios in cards, so it has one tab stop and arrow keys and submits with a form. A `radiogroup` named by its Field's label, with `v-model`, selected and disabled states, and an optional result bar and percentage (`show-results`, or the `result` slot).
- **`<EmojiTray>`** ([docs/emoji-tray.md](docs/emoji-tray.md)): a compact reaction picker. A labelled `toolbar` with a roving tabindex, a spoken label per emoji, and a `react` event throttled by `throttle` ms (presses inside the window are dropped). It has `DEFAULT_REACTIONS` and reduced motion.
- **Button `size="lg"` and `size="xl"`** ([docs/button.md](docs/button.md)): `lg` is the host button's; `xl` is the host's lg made a ~96px tap target with kit classes.
- **Button `href`:** a link styled as the button, never a `<button>` inside an `<a>`. It's an Inertia `<Link>` (`method`, `data`, `preserve-scroll`; a non-GET method renders as a `<button>`), or a plain `<a>` with `external` (`rel="noopener noreferrer"` for `target="_blank"`). `VisitData` is exported.

## v0.5.4

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:598510bf…b128` → `sha256:f1e58788…5cf7`): new `Layout/DescriptionList.vue` and `Feedback/EmptyState.vue`; `index.ts` and `contract.ts` change.

### Added

- **`<DescriptionList>`** ([docs/description-list.md](docs/description-list.md)): term and description pairs as a real `<dl>`, from `items` or slots. Inline (side by side from `sm`, stacked on a phone) or stacked, with an optional copy-to-clipboard button per value and a polite status.
- **`<EmptyState>`** ([docs/empty-state.md](docs/empty-state.md)): an icon, a title, a body and the next step, as an `action` slot or server action descriptors. Has a `compact` form for panels and lists, and is a plain labelled region, never a live region. Both components are exported from the barrel, with `DescriptionListProps` and `EmptyStateProps` in the contract.

## v0.5.3

### Upgrading

- **Upgrading from v0.4.7 or earlier, with your own dashboard widgets:** since v0.4.8, `DashboardController` renders the kit's Registry widgets itself and returns `toResponse()`, so an app override that injected its own widgets breaks. Register them with `Registry::widget()`, or set `kit.dashboard.widgets` to `[]` (now also noted under v0.4.8 and in [docs/dashboard.md](docs/dashboard.md#upgrading-an-app-with-its-own-dashboard-widgets)).
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a2d9f179…bf85` → `sha256:598510bf…b128`): `Form/FormActions.vue` changes.

### Added

- **`kit.mcp.tools`** turns off individual kit MCP tools by name, for an app that serves its own: `['workspace_dashboard' => false, 'workspace_search' => false]`. A tool that is off is neither listed nor callable. The default (`[]`) leaves every tool on. `composer test:dashboard` covers it.
- **Responsive conversions** ([docs/media.md](docs/media.md#conversions)): `HasResponsiveImages::responsiveWebp($collection, $widths)` declares `w<width>` WebP conversions, fit within the width and never upscaled. `PerformConversions` runs them one image per job on `kit.media.queue` (default `media`), with tries, backoff, a timeout and a memory limit; a retry does only what's missing. It implements spatie's `TenantAware` (it carries and runs in its tenant) and fails loudly with no current tenant. `Conversions::regenerate()` is idempotent. `Srcset::for()` lists only generated widths, with the original as the fallback, over the app's own URLs. `AttachUpload` queues an item's conversions. `composer test:media-conversions` covers it.

### Fixed

- **ViewModel props come in the same order on every PHP version.** `propMethods()` (and so `toArray()` and the generated TypeScript) kept reflection's order, which PHP 8.4 and 8.5 give differently for trait and inherited methods. The order is now ancestors first (root to child), then within each class its traits' methods in `use` order and its own by line; an override keeps its parent's slot. `composer test:viewmodel-order` covers it on both versions.
- **A typed `useForm<T>()` passes `vue-tsc` on FormActions and FormCard.** `FormActions`' `FormLike` declared `reset` and `clearErrors` as function-typed properties, so a form whose `reset` takes only `T`'s keys failed with TS2322. They are now methods (bivariant). The components type-check job compiles a fixture passing `useForm<{ a: string; b: { c: number } }>()` to both.

## v0.5.2

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:35504c18…cd67` → `sha256:ceb00b2e…ce1e`): BulkDrop changes; new `Form/directUpload.ts`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ceb00b2e…ce1e` → `sha256:a2d9f179…bf85`): ActionBar and `Action/actionFit.ts` change.

### Added

- **BulkDrop `direct`:** with `request` and `confirm` routes, BulkDrop uploads each file straight to the bucket with progress and emits `uploaded`. `directUpload()` is the same flow without the dropzone. A POST ticket falls back to multipart, and retries reuse each File's mutation id.

### Fixed

- **ActionBar no longer triggers a ResizeObserver loop.** Its observer measured and re-laid out the bar inside its own callback, so flipping between the compact and full layouts resized the observed root in the same frame ("ResizeObserver loop completed with undelivered notifications", which fails browser tests). It now measures in one coalesced animation frame, skips a measure that hasn't changed, and cancels the frame and disconnects on unmount. The fit has hysteresis: a compact bar returns to the full layout only with `ACTION_FIT_HYSTERESIS` (8px) to spare, so a width at the boundary can't oscillate. `fitActions()` takes the previous state as an optional third argument, and `sameMeasure()` is new.

## v0.5.1

### Upgrading

- Run `kit:update plan|apply` for `workspaces` again (`sha256:9d17553b…b75f` → `sha256:9243696e…5a0f`): its workspaces, permission and slug migrations now adopt an existing table in place, and it declares the `workspace_model` host step. Nothing changes for an existing kit host: the migrations already ran, the kit's `Models\Workspace` stays the default binding, and the step is met by the defaults.
- **Binding your own workspace model** (optional): set `concierge.models.workspace` and `multitenancy.tenant_model` to the same class, which extends spatie's `Tenant`, implements `Tey\Concierge\Contracts\Workspace` and uses `Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace`. See the README's "Adopting into an existing workspaces table".
- Code that type-hinted `Tey\Kit\Features\Workspaces\Models\Workspace` against a kit method's return value now gets the Concierge contract type; the object is still the bound model.
- Kit signatures that took the kit model now take the contract, so an extender overriding one widens its parameter to match: `public function handle(Models\Workspace $workspace)` → `public function handle(\Tey\Concierge\Contracts\Workspace $workspace)` (likewise `Workspace::query()` → `Workspaces::query()`, `Workspace::required()` → `Workspaces::required()`).
- Run `composer update tey/concierge`: the kit now requires `tey/concierge` ^0.8.4, for the workspace context's `enter()`/`leave()`. Then run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:9243696e…5a0f` → `sha256:0c427f2f…92f8`). An app with its own `WorkspaceContext` implementation must add `enter()` and `leave()` (see Concierge's upgrade notes).
- **A host with its own `config/multitenancy.php`** (or its own `switch_tenant_tasks` list) must add the kit's two tasks, or a TenantAware job or command runs without the Concierge context and with a stale Pennant cache:

  ```php
  'switch_tenant_tasks' => [Tey\Kit\Features\Workspaces\Tasks\ConciergeContextTask::class, Tey\Kit\Features\Workspaces\Tasks\FlushFeatureCacheTask::class],
  ```

### Added

- **Adopt the workspaces feature into an existing workspaces table.** A host with its own `workspaces` table and spatie/laravel-permission's tables (with teams) gets planned adoptions (`adoptions`, `HostRequirements::adoptions()`) instead of collisions. The workspaces migration adds only `admitted` (existing rows set to true), `settings_version` (set to 1), `provisioning_invitation_id` and `slug` where missing, and never drops or alters a host column. The permission migration creates nothing when the five tables exist, and refuses tables without the configured teams column. Each adoption is recorded in a kit-owned `kit_adoptions` table (table, columns added, when); `down()` reads it, never drops an adopted table, and drops only the columns the kit recorded adding.
- `Tey\Kit\Features\Workspaces\Workspaces` resolves the bound workspace model (`model()`, `query()`, `current()`, `required()`, `refusal()`). Every kit reference goes through it, and `composer test:workspace-adopt` fails if anything under `src/` names `Models\Workspace` again.
- `IsKitWorkspace` carries the kit model's behaviour (slugs, `memberOf`, Concierge naming and admission, `required()`) for a host model to use. The kit model keeps only its no-timestamps overrides.
- The `workspace_model` host step: a plan refuses a binding where `concierge.models.workspace` and `multitenancy.tenant_model` differ, or the model misses the contract, `IsTenant` or the trait.
- `composer test:workspace-adopt` (SQLite memory, synthetic host schema), and a CI job running it against a PostgreSQL service container (`DB_CONNECTION=pgsql`). Not covered: MySQL, and an end-to-end `kit:add` against a real host app.
- **Switch tenant tasks:** making a workspace the current tenant (spatie's `makeCurrent`, `Tenant::execute`, a TenantAware job or command) now does what TenantBoundary did:
  - `ConciergeContextTask` enters the workspace in Concierge's context (the current workspace and the permission team), and `forgetCurrent` leaves it, restoring what was there before;
  - `FlushFeatureCacheTask` flushes Pennant's cache both ways.

  They're registered in the kit's multitenancy defaults. `composer test:tenant-tasks` covers them, including that a TenantAware job with no tenant fails loudly.

## v0.5.0

### Upgrading

- **`SurfaceContext`:** the barrel's type export for a surface's tone and shape (`Surface` from `Surface/surface`) is now `SurfaceContext`. Exporting it as `Surface` as well as the `<Surface>` component was a duplicate identifier (TS2300) under vue-tsc. Change `import type { Surface } from '@kit/ui'` to `import type { SurfaceContext } from '@kit/ui'`. `provideSurface`/`useSurface` and `Surface/surface` are unchanged.
- Run `kit:update plan|apply` for `core` (index.ts, Layout/Collapsible.vue, Surface/Surface.vue, contract.ts).
- **Surface clips by default** (`clip`, default `true`, is `overflow-hidden`). If something inside a Surface must overflow it or stick (a sticky header, an absolutely positioned popover), pass `:clip="false"`.
- **session-ui splits into session-ui (the sign-in) and workspace-ui (the workspace pages).** Re-pinned: `session-ui` `sha256:c0730ecd…9e0d` → `sha256:cc31f07b…ae1f`, `notes` `sha256:1d5fe0eb…9370` → `sha256:d373d438…f1b2` (it now requires workspace-ui); new `workspace-ui` `sha256:e5f861f9…6950`. No file's bytes change and nothing is deleted: 22 files change owner, from session-ui to workspace-ui. After `composer update`, and before any of the steps below, the app keeps its workspace pages on: workspace-ui is carried while session-ui's installed lineage still owns them. Move them over with these runs, in this order. Each `plan` prints the `apply` command to run (with its `--expected-plan`, `--exclusive-checkout` and `--intent`), and each result's `next` names the following run:
  1. `php artisan kit:update plan --lineage=session-ui`, then its `apply`. session-ui releases the 22 workspace pages (each file's state is `release`; none is written or deleted) and goes to an interim version. This is also the update that makes it own only its three welcome-step folders under `pages/Welcome`, not the whole folder.
  2. `php artisan kit:update plan --lineage=workspace-ui`, then its `apply`. workspace-ui is installed at exactly the released bytes: it claims the 22 files and writes nothing.
  3. `php artisan kit:update plan --lineage=workspace-ui` again, then its `apply`: workspace-ui goes to its target (nothing to write).
  4. `php artisan kit:update plan --lineage=session-ui` again, then its `apply`: session-ui finishes.
  5. If the app has notes: `php artisan kit:update plan --lineage=notes`, then its `apply`. It requires workspace-ui, so it runs after step 2.
  6. If `config/kit.php` sets `kit.features`, add `'workspace-ui'` after `'session-ui'` (the app works without it, as workspace-ui is carried once its lineage is installed).

  If you have edited one of the moved pages (under `pages/Workspace/` or `pages/Provision/`), step 1 writes nothing and prints the diff from the installed file to yours: put your changes aside, restore the installed bytes, run the steps, then reapply your changes. Needs tey/mod with removals (main, 0.11), as the shell split did.
- Nothing else changes unless you set `kit.home`: the kit's home stays at `/`.

### Added

- **Surface `clip` and `translucent`** ([docs/surface.md](docs/surface.md)). `clip` (default `true`) is `overflow-hidden`; set it `false` for a sticky child. `translucent` is an 80% fill with a backdrop blur, solid without `backdrop-filter` support and under `prefers-contrast: more` or `prefers-reduced-transparency`, and unblurred under `prefers-reduced-motion`. Surface now clips by default: a host relying on a Surface's content overflowing it (a popover inside, a sticky child) passes `:clip="false"`.
- **CI type-checks the core components with vue-tsc** (`bin/typecheck-components.sh`, versions pinned in `typecheck/package.json`). The host's shadcn-vue components are typed `any` there (`typecheck/shims.d.ts`), so the check covers the kit's own code, not its use of them.
- **Auth-only session-ui.** A host can adopt only the sign-in: `kit:add session-ui` brings `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/TwoFactor`, `Auth/VerifyEmail` and `Auth/Invitation/Show`, the welcome steps and the routes around them, and none of the workspace pages. Without workspace-ui the kit serves nothing at `kit.home`, and Fortify's `home` (where sign-in lands) defaults to `kit.home`, the app's own page; an accepted invitation and the last welcome step land there too. `composer test:auth-only` and `composer test:host-boot` cover it.
- **`workspace-ui`**, a new feature requiring session-ui: the kit's home, the dashboard and its widgets, search, the settings and account landing pages, Settings › People, the account pages under `/me`, the placeholders, the error page, `Workspace/Index` and `Provision/NoWorkspace`, moved from session-ui byte for byte. Its routes keep their names and `kit.routes.session-ui` options, and its classes keep their `SessionUi` names. `composer test:workspace-ui` covers the split and the move.
- **`kit.home` (`KIT_HOME`):** where workspace-ui serves the kit's home, `/` by default. An app with its own public landing at `/` sets it elsewhere (`/app`); the kit's home and the breadcrumbs' root move there, and the app's `/` is untouched.

### Fixed

- **vue-tsc failed inside the kit's core components** (TS2300 in `index.ts`; TS2769 and TS2322 in `Layout/Collapsible.vue`). Collapsible's `open` model is now `defineModel<boolean | undefined>('open')`: bound, the host's value wins; unbound, it starts from `defaultOpen`, and nothing is emitted until the user toggles (it used to write the model while setting up).
- **An auth-only app has no links to workspace pages.** Without workspace-ui, `/me/workspaces` (and the old `/workspaces`) redirects to `kit.home` instead of rendering a chooser page the app does not have; Concierge's `no_workspace_url` defaults to `kit.home` (an app's own `config/concierge.php` decides for itself); and the shared shell's home, switcher, product, account and "All workspaces" links point at `kit.home`, with no "Account settings" item. The workspace guard's and leave-workspace redirects use the same `WorkspaceUrl::chooser()`. With workspace-ui nothing changes. `composer test:auth-only` and `composer test:host-boot` cover it.
- **session-ui no longer owns a host's landing page.** It owned all of `resources/js/pages/Welcome`, so an app whose landing lives at `pages/Welcome/Page.vue` (with `Partials/`) collided with the kit there. It now owns only `Welcome/Timezone`, `Welcome/Photo` and `Welcome/WorkspaceTimezone`. `composer test:host-boot` covers the ownership and `kit.home`.

## v0.4.33

### Added

- **Direct-to-bucket uploads** (`Tey\Kit\Media`, [docs/media.md](docs/media.md)): `RequestUpload` reserves a pending upload from a declared type, size and SHA-256 and returns a presigned PUT that binds them (or a multipart `POST` ticket when the disk cannot presign); `ConfirmUpload` checks the object by size and its header bytes (a hash on a local disk), and `AttachUpload` makes it a Media item over the existing key with no byte copy. Mutation replay, dedupe by SHA-256 within a scope. `KeyPathGenerator` and `KeptFileRemover`: deleting a Media item over a key never deletes the object. A library, not a catalog feature, so no re-pin. `composer test:media-uploads` covers it.

## v0.4.32

### Upgrading

- Run `kit:update plan|apply` for `session-ui` again (`sha256:f2cc2fd1…3670` → `sha256:cd013c29…abae`): it adds `Auth/ForgotPassword` and `Auth/ResetPassword`, and the sign-in page links to them.
- **Password reset is on:** the kit's fortify defaults now include `Features::resetPasswords()`. The host needs Laravel's `password_reset_tokens` table and mail that can send the reset notification. An app that publishes its own `config/fortify.php` keeps its own feature list.
- Run `kit:update plan|apply` for `core` (Button, FormActions, FormCard, ActionBar, ActionMenuItems, InputCheckbox; new `Surface/Surface.vue`, `Form/formSubmit.ts` and `Action/menuSelect.ts`) and `session-ui` (its form buttons now say `type="submit"`).
- **Behaviour change: a kit `Button` is `type="button"` by default.** It's a submit only inside Inertia's `<Form>` or a FormCard, or when you pass `type="submit"`. Give any of your own kit Buttons that submit a plain `<form>` (or a form outside FormCard and `<Form>`) an explicit `type="submit"`. A toolbar or menu Button inside a form no longer submits it by accident.
- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:8aa53c04…717f` → `sha256:9d17553b…b75f`): it now requires `tey/concierge` ^0.8.3. Run `composer update tey/concierge`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4e8ab9c4…960a` → `sha256:519f5f64…afb0`): ActionBar, FloatingToolbar, WorkbenchStage, `index.ts` and `contract.ts` change; new `Action/actionFit.ts`, `Layout/toolbarAnchor.ts`, `Layout/toolbarAction.ts`, `Layout/Collapsible.vue` and `Workbench/workbenchStage.ts`.
- ActionBar's root is now a wrapper `div` carrying `data-action-bar` (around the Toolbar), and it no longer uses `sm:`/`max-sm:` classes to compact. A host stylesheet or test that targeted the old structure needs updating.

### Added

- **Forgot your password?** Kit pages for Fortify's password reset: `Auth/ForgotPassword` asks for the emailed link, and `Auth/ResetPassword` chooses the new password with the rules shown, under the same policy and breach message (`Fortify\ResetPassword`). The sign-in page links to it. With Concierge's reset-verifies rule, this is also how the first owner from `concierge:owners` sets a password and lands verified. `composer test:onboarding` covers it.
- **`<Collapsible>`** (`Kit/Layout/Collapsible.vue`, [docs/collapsible.md](docs/collapsible.md)): a disclosure section for inspector sections. Its toggle is a real `<button>` with `aria-expanded` and `aria-controls` naming the content region, the content is hidden while closed, it has `v-model:open` (else `defaultOpen`), a `title` prop or `summary` slot, an `actions` slot and `level`, and its transition is off under reduced motion. `CollapsibleProps` is in the contract.
- **FloatingToolbar client actions:** `items` (`ToolbarAction`: `key`, `label`, optional `icon`, `disabled`, `reason`; no URL) handed to the host's `onAction` (`@action`) by the toolbar's own handler (`runToolbarAction`). Server descriptors through a slotted ActionBar work as before.
- **FloatingToolbar `mode="stage"` and `mode="docked"`** inside a Workbench: `stage` floats over the bounding box of the selected stage elements (`wb.elements`, found by `data-workbench-element`), above them, flipping below and clamped inside the stage (`anchorToolbar`, `unionRect`); `docked` renders in the stage toolbar row. A `reposition()` is exposed.
- **The Workbench stage context:** WorkbenchStage provides `provideWorkbenchStage({ workbenchKey, viewport, toolbar })`; `useWorkbenchStage()` and `selectedRects()` are exported ([docs/workbench.md](docs/workbench.md#editing-toolbars-and-narrow-actions)).
- `fitActions`, `ToolbarAction`, `AnchorRect`, `ToolbarAnchor` and `WorkbenchStageContext` are exported from the barrel.
- **`<Surface>`:** a card-like container (padding, border, radius, `bg-card`/`text-card-foreground`) that provides its tone and shape to what's inside (`provideSurface`), so a kit Button or ActionBar on it blends in. Props: `tone` (default, inverse), `shape` (default, pill), `padding` (none, sm, md), `as`. Exported from the barrel; `SurfaceProps` is in the contract.
- **Button:**
  - `ghost` and `link` variants (the host button's own, so the host needs no new variant);
  - a `size` prop (default, sm, icon; the host button's sizes).
- **FormActions (and FormCard):** `dirtyLabel`, `errorsLabel` and `upToDateLabel` override the status lines, alongside `savedLabel`.

### Fixed

- **ActionBar didn't compact inside a Workbench at narrow widths.** It compacted on the viewport's `sm` breakpoint, so a narrow Workbench actions bar on a wide viewport (or a 390px one whose bar is narrower still) wrapped instead. It now measures its own width with a ResizeObserver and falls back to its main action and a ⌄ menu whenever its full layout doesn't fit (`fitActions`), for the pill and the default surfaces alike.
- `composer test:js` (`tests/js/workbench-editor-gaps.test.mjs`) covers each item: the pure fit, anchor and action logic, and the components' sources. There's no browser test.
- **A menu action chosen from ActionBar's ⌄ menu reached nothing.** `ActionMenuItems` emitted the action on a timer after the menu closed, but the menu had already unmounted it, so Vue dropped the emit: every non-dialog, non-document menu action (click or Enter) was lost. The rows now call an `onAction` handler captured when the row is chosen; ActionBar passes its own, which stays mounted. A host's `@action` on ActionMenuItems still works.
- Documented:
  - `warnOnLeave` false registers no leave guard at all (no router `before` listener, no `beforeunload`), so a host can own its own guard;
  - InputCheckbox requires an `id`.
- `composer test:js` (`tests/js/editor-primitives.test.mjs`) covers each item.

### Changed

- **Concierge verifies the invited address.** Concierge 0.8.3 verifies an unverified account whose email is the invited one inside the accept transaction, for member and tenant invitations, and shows such an invitation as ready to accept. The kit's own stopgap (`Workspaces\InvitedEmail`, v0.4.31) is removed. Opening an invitation no longer verifies on its own; accepting it does. `composer test:onboarding` still proves every kit invite path verifies.

## v0.4.31

### Upgrading

- Run `kit:update plan|apply` for `core` (`sha256:7c1ae722…8b43` → `sha256:4e8ab9c4…960a`), `shell` (`sha256:cf7ec863…ded3` → `sha256:8adcf2e2…2024`), `session-ui` (`sha256:30fb141d…8a8e` → `sha256:f2cc2fd1…3670`). It adds `Kit/Form/PasswordRules.vue`, `Kit/Feedback/PasskeyOffer.vue` and `passkeyOffer.ts`; `PasswordInput` gains `rules`; the invitation sign-up and change-password pages show the rules; and the shell layout renders the passkey offer.
- **New password policy:** `Password::defaults()` is now `Password::min(8)->uncompromised()` with no composition rules (NIST 800-63B), set by session-ui. Existing users aren't asked to change their password; the rule applies the next time a password is set. An app that sets its own `Password::defaults()` keeps it, and the rules under the field follow it.
- Passkeys after sign-up are optional: see `docs/onboarding.md` (Fortify's `passkeys` feature, laravel/passkeys' migration, and `PasskeyUser` on the User). Without them nothing changes.

### Added

- **One onboarding path, one email** (docs/onboarding.md): invitation email → invitation page → create the account → welcome → workspace, all on kit screens.
  - An invited sign-up sends exactly one email, the invitation. The account is created verified, so a host's `Registered` → `SendEmailVerificationNotification` sends nothing.
  - An unverified account whose email is the invited one is verified when it opens or accepts a member invitation, or accepts a workspace (tenant) invitation. This is a stopgap until Concierge does it upstream.
- **Password rules up front:** `<PasswordInput rules />` (`PasswordRules`) shows "At least 8 characters", ticked once met, and "Not found in a known data breach (checked when you submit)". The values come from `Password::defaults()`, shared as `kitPassword`.
- **A plain breach message:** "This password has appeared in a data breach, so it isn't safe. Pick another.", from `kit::validation.password.uncompromised`.
- **"Add a passkey for next time"** after an invited sign-up, on Fortify's passkey registration. It's dismissible and shown only where the app has passkeys.
- `composer test:onboarding` and `composer test:onboarding-js`, both in CI.

## v0.4.30

### Upgrading

- Run `kit:update plan|apply` for `core`: it adds `Kit/Feedback/StageNotice.vue` and `stageNotice.ts`.

### Added

- **`<StageNotice>`** (docs/stage-notice.md): an announcement laid over a stage or slide, for example "Live demo: <label>" on a projector.
  - It's positioned inside its container, sized in container-query units, and `pointer-events: none`; its content is the default slot.
  - It has Alert's tones (info, success, warning, danger; solid or pale) and a top, bottom or corner placement.
  - Its enter and leave transition only moves when motion is allowed.
  - It's exported from the kit barrel, with `StageNoticeProps` in the contract. `composer test:js` covers it.
## v0.4.29

### Upgrading

- Run `kit:update plan|apply` for `core`. `resources/css/kit.css` gains the default colour tokens, and core declares a `theme` host step. A host whose stylesheet already imports kit.css, or defines the tokens, sees no change: its theme overrides the defaults.

### Added

- **Default colour tokens in kit.css.** The kit's components are coloured with shadcn-vue's semantic tokens, so a host theme without them (a host without the Laravel starter kit's shadcn layer) rendered them uncoloured. kit.css now defines all 18 at the lowest precedence:
  - an `@theme default inline` mapping (`--color-primary: var(--primary)` and so on), which any host `@theme` replaces;
  - the starter kit's neutral light and dark values on `:where(:root)` and `:where(.dark)`, which any host `:root` or `.dark` overrides.
- **A `theme` host step (core: `resources/css/app.css`).** `kit:add` and `kit:update` plans check that the host's stylesheet imports kit.css or defines every token. If not, attention names the missing tokens and the import; it never blocks.
- `composer test:plan-steps` covers the step on a host fixture without the tokens, with kit.css, with a partial theme and with a full one. It also checks that kit.css maps every token with light and dark values, and that every semantic token a kit stub uses has a default.

## v0.4.28

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:8050a73e…65d3` → `sha256:f902dba8…a247`): it adds `Kit/Workbench/WorkbenchCollapsedBar.vue`, and `Workbench.vue` and `WorkbenchStage.vue` render it.

### Fixed

- **A collapsed Workbench pane keeps a way back.** Collapsing the footer (or the navigator or inspector) also hid its header, which held the only Expand toggle, leaving just a hairline resize handle. A collapsed pane now keeps a slim 2rem bar with its label and an Expand button. The button is in the tab order, with `aria-expanded="false"` and `aria-controls` naming the pane's region. It sits beside a side pane at xl and lg, or under the stage for the footer. Expanding goes through the panel's own `expand()`, which restores its remembered size, and ⌘[ / ⌘] work as before. `composer test:workbench-js` checks each region's bar, its wiring and its aria.

## v0.4.27

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:3aed4d27…db5f` → `sha256:8050a73e…65d3`): the `Kit/Workbench/` files take the four fixes below, and `@kit/ui` exports `InspectorScope`.
- An app that reset the Workbench selection itself on each visit, or added its own document Esc listener, can drop that workaround.

### Fixed

- **Esc after a stage click.** A click on a plain stage element left focus on `<body>`, so the Workbench never saw the Esc. The document listener now handles Esc when focus is on `<body>` or inside the Workbench. It never acts from a field that takes typing, nor while another dialog or a menu is open.
- **Item and element inspectors no longer collide.** `defineInspector(type, component, { for: 'elements' | 'items' | 'any' })`: element selections consider only `elements` and `any` inspectors, item selections only `items` and `any`, so a slide layout and a block type of the same name each get their own. `any` stays the default.
- **No duplicate save status.** `<Workbench save-status="none">` renders no kit status region or conflict alert, for an app whose own form bar in `#actions` announces the save state. `wb.saveState` stays readable, and `kit` stays the default.
- **The selection doesn't outlive an Inertia visit.** The per-key store outlives a visit, so arriving at another item by back or a link kept stale elements. Each Workbench mount, and every change of the server's `current` prop, now selects `current` again and clears the stage elements. Pane prefs and zoom stay per key.
- `composer test:workbench-js` covers each fix.

## v0.4.26

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:38d62d72…7573` → `sha256:3aed4d27…db5f`): the `Kit/Workbench/` files gain stage element selection, and `@kit/ui` exports `WorkbenchElement`.

### Added

- **Workbench stage elements** (docs/workbench.md): the stage can select elements inside the current item (blocks on a slide), and they pick the inspector, so the inspector follows the stage's selection and not only the navigator's.
  - `wb.selectElement(id, type, mode | event)`, with ⌘, Ctrl or Shift to multi-select; `wb.selectElements([{ id, type }])`; `wb.clearElements()`; and a read-only `wb.elements`.
  - Elements aren't pruned by `items`, and they're cleared when the current item changes.
  - **Inspector resolution:** selected elements decide first, by element type (one type → that inspector; mixed types → a common inspector or `@fallback`). Otherwise the item selection decides, as before, and with neither it's `@document`.
  - **Inspector props:** inspectors now receive `{ element, elements, ids, items, types, current }`. The existing props are unchanged.
  - **Esc** closes the drawer, otherwise clears the elements, otherwise clears the item selection (not while typing). F6 is unchanged.
  - `composer test:workbench-js` covers it.

## v0.4.25

### Fixed

- **No Mod warning on every boot under another layout.** Under a layout other than `laravel`, Mod logged `Scaffold [tey/kit] makes a starter-policy, which the … layout doesn't have, so mod:tey/kit is disabled.` each time it resolved commands, because the kit registered its legacy v0.1 `kit:foundation` scaffold anyway. The kit now registers that scaffold only under `laravel`, or when `kit.scaffold_features` is configured. Otherwise a stand-in `kit:foundation` refuses with the same message as before and writes nothing. Hosts don't need a fake `starter-policy` generator. No stub changed, so there is nothing to re-pin. `composer test:layout` now checks that boot and command resolution log nothing under both layouts.

## v0.4.24

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:30386b0a…ee06` → `sha256:38d62d72…7573`) and `notes` (`sha256:640b093e…53d2` → `sha256:1d5fe0eb…9370`).
  - core: `Kit/Form/InputSelect.vue` and `inputSelect.ts` keep only the custom properties of a caller's `style`, and `InputSelectProps` gains `popoverClass`.
  - core adds `Kit/utils.ts` (the kit's own `cn`, which BlockStack and Toolbar import instead of `@/lib/utils`) and `Kit/types.ts`.
  - `Kit/types.ts` holds the two Inertia types the kit uses (`Method`, and a minimal `InfiniteScrollRef`). splitState, useScrollList and the Workbench import them from there instead of `@inertiajs/core`. The notes composer's form transform is generic over Inertia's own form-data type, so it names none.
- **The host needs the UI primitives core imports.** `kit:add`, `kit:update plan` and `kit:adopt plan` now block without them and print the command, for example `npx shadcn-vue@latest add button checkbox dropdown-menu input label sheet spinner`. An app built from the Laravel starter kit already has them.
- core's npm requirements now name `@inertiajs/vue3` (not `@inertiajs/core`), `clsx` and `tailwind-merge`. If your `package.json` declares `@inertiajs/core` beside `@inertiajs/vue3`, remove it (the plan points it out).
- A caller that styled an InputSelect with ordinary properties through `style` should move them to `popoverClass` (for the list) or `class` (for the field). `--*` custom properties still pass through.

### Changed

- **InputSelect forwards only custom properties.** It forwarded a caller's whole `style` to its popover wrapper, the one inline-style exemption left after v0.4.23. It now keeps only the `--*` custom properties, from a string, an object or an array (the new `customProperties()` in `inputSelect.ts`), and sets them on the field's wrapper and on the portaled list, so a caller can theme the list through variables. The new `popoverClass` prop styles the list otherwise. The style-conventions check in `composer test:stubs` now has no exemptions: a caller's style may only reach a stub through `customProperties(...)`. `composer test:input-select` checks that a caller's `--x` is kept and `color: red` is dropped.

### Fixed

- **`kit:add core` no longer previews clean on a host without shadcn-vue's primitives.** core imports `button`, `checkbox`, `dropdown-menu`, `input`, `label`, `sheet` and `spinner/Spinner.vue` from `@/components/ui`, but declared only `spinner`, and nothing checked `host_ui`. So such a host got a clean preview, then 17 vue-tsc errors and a broken build.
  - core now declares every primitive, and the library check fails when a stub imports one its feature doesn't declare.
  - Each missing primitive (`<name>/index.ts`, or the declared file) is a blocking `host_ui` step with the exact command, in kit:add and in update and adopt plans (from the primitives the kit's files import).
  - Each primitive's outcome is bound into the plan identity and reported under `host_ui`.
- **No dependency on the host's `@/lib/utils`.** The kit ships its own `cn` (clsx, then tailwind-merge) at `resources/js/components/Kit/utils.ts`.
- **No second copy of `@inertiajs/core`.** `@inertiajs/vue3` pins `@inertiajs/core` exactly, so a host told to add `@inertiajs/core ^3.0` could install a newer copy beside vue3's own, which broke PageProps typing. core now requires `@inertiajs/vue3 ^3.0` instead, and no stub imports `@inertiajs/core`: it would be an undeclared import, which standards' js-imports rule flags and pnpm can't resolve. A declared `@inertiajs/core` at another version than vue3 pins, or two installed copies, raises attention with the fix (an exact match, like the starter's, is one copy and passes). The library check fails on any `@inertiajs/core` import in a stub.
- `composer test:plan-steps` and `composer test:library` cover all of this.

## v0.4.23

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:0e1405b6…593b` → `sha256:30386b0a…ee06`), `shell` (`sha256:03039592…65da` → `sha256:cf7ec863…ded3`) and `session-ui` (`sha256:76d9be3d…be1f` → `sha256:30fb141d…a8e`): `Kit/Feedback/Toaster.vue`, `Kit/Form/FormActions.vue`, `Workspace/Layout.vue` and `Workspace/Dashboard/Page.vue` set their geometry and colour through custom properties.

### Changed

- **No inline property styles in kit stubs.** The anchored toast's position, the upload progress bar's width, the dashboard meter's width and the environment edge's colour were set with inline `style` properties. Each binding now sets only a custom property (`--kit-toast-top`, `--kit-toast-left`, `--kit-form-progress`, `--kit-meter`, `--kit-environment`), and a utility class reads it (`top-(--kit-toast-top)`, `w-(--kit-form-progress)`, `bg-(--kit-environment)`). What renders is the same. `composer test:stubs` now also fails on any `style=` or `:style` in a kit stub that sets anything but `--*` custom properties. InputSelect's forwarding of the caller's own `style` is the one exception.

## v0.4.22

### Fixed

- **The kit boots under any tey/mod layout.** `KitServiceProvider::boot()` threw `Blueprint requires the unmodified laravel layout.` on every request when `mod.layout` wasn't `laravel`, so a host with its own layout (a layout extending `modules`, say) couldn't boot with the kit at all. Kit-owned files always use their canonical paths, never the host's layout, so the boot guard is gone. Only the legacy v0.1 `kit:foundation` scaffold still refuses another layout, when it is run, with a message that says kit features work under any layout. No stub changed, so there is nothing to re-pin. The new `composer test:layout` runs `kit:install`, `kit:add core`, `kit:status`, `kit:update plan` and `kit:adopt plan` under a layout extending `modules` (with an `app/UI` mount), and checks the files and the lineage identity match a `laravel` host. `docs/catalog.md` says which paths kit files use.

## v0.4.21

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c3a4a8af…d8dd` → `sha256:0e1405b6…593b`): `Kit/Form/TimezoneSelect.vue` is now an InputSelect, and `TimezoneSelectProps` gains `autofocus`.
- Run `kit:update plan|apply` for `settings` (re-pinned `sha256:d3afc44b…a193` → `sha256:fb83eb1d…f1e`): it adds the migration `0001_01_02_000402_adopt_display_name_as_workspace_name.php`.
- An app that overrode `Settings\Operations\WorkspaceProfile` to read and rename the workspace's own name can drop that override and its binding: the kit now does the same.
- A workspace's General display name now becomes its workspace name: `php artisan migrate` runs the settings migration `adopt_display_name_as_workspace_name`, which copies a non-empty `display_name` that differs from `workspaces.name` into it, once, and moves `settings_version`. Empty or equal values are left alone, and a rerun does nothing.

### Fixed

- **One workspace name.** General read and saved a `display_name` setting, while the shell and its workspace switcher showed the workspace's own name, so the two could disagree. General now reads the workspace's name and renames the workspace inside the same version-checked transaction (row lock, `settings_version`, intent replay, `stale_settings`). `display_name` stays as a compatibility mirror, and the settings cache is republished after a rollback. The save redirects, and the `shell` share is computed per request, so the next page's switcher shows the new name. `composer test:permissions-http` covers the rename, a newer rename surviving a replay, a stale rename, and a stale mirror.
- **Searchable timezone picker.** `TimezoneSelect` was on the deprecated Reka `Select`, with no search over about 400 zones. It now renders the searchable InputSelect, with the current zone first, then the browser's zone, then the rest A to Z. It is used on General and the two welcome timezone steps. `composer test:stubs` now also runs a conventions check that fails on a native `<select>` or a timezone on the deprecated `Select` in any kit stub.

## v0.4.20

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:fbca3794…c3f4` → `sha256:c3a4a8af…d8dd`): it adds `Kit/Workbench/` and the Toaster's `kit:toast:show` listener. session-ui and mcp change in `src` only.

### Added

- **Workbench** (docs/workbench.md): `<Workbench workbench-key>`, the kit's multi-pane editing surface for a sequence of items with a live preview.
  - **Regions:** `navigator`, `stage` (with an optional sticky `toolbar`), an optional resizable and collapsible `footer`, `inspector`, and an actions bar on StickyBar.
  - **Layout per width:** three resizable, collapsible columns from xl; navigator and stage with an inspector drawer at lg; tabs below lg (the footer's tab only when provided).
  - **Remembered layout:** pane sizes, collapsed panes, zoom and the phone tab, per person and key.
  - **`useWorkbench(key)`:** single and multi-select with the current item and next/prev; dirty and save state; `onRecalculate` with a stale guard; `defineInspector` (selection-driven: `@document`, per type, a common inspector for mixed types, `@fallback`); optimistic reorder through the app's action with a version token, rolled back with a toast on a conflict; stage zoom and fit.
  - **Keyboard:** ↑/↓ and j/k, Alt+↑/↓, ⌘[ / ⌘], `/`, Esc, F6, ⌘+ / ⌘- / ⌘0, with ARIA landmarks.
  - **Server:**
    - `WorkbenchViewModel`, with `WorkbenchItem`, `WorkbenchInspector` and `WorkbenchLayout` data.
    - session-ui's `PUT /me/workbenches/{key}`: versioned, with 409 `stale_write`, kept in `kit_user_state`, so no migration.
    - mcp's optional, read-only `WorkbenchReadTool` base.
  - **Checks:** `composer test:workbench` and `composer test:workbench-js`, both in CI.
- **`kit:toast:show`:** the Toaster renders a toast sent by a kit component that acts without a page visit. Pages still never toast.

## v0.4.19

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:d35bf35a…1248` → `sha256:fbca3794…c3f4`): `Kit/Feedback/Toaster.vue` and `toastPlacement.ts` deduplicate by toast id.

### Fixed

- **A toast on a full page load shows once.** On a non-Inertia redirect that carries a flash (an OAuth return, say), the Toaster showed the flash on mount and again from the router's first `flash` event, because that event carries an equal but different object and the check was identity-only. Each flashed toast now carries a unique `id` (a ULID, kept across its own re-flashes), and the Toaster shows each id once, remembering a few recent ones. It doesn't compare content, so two identical saves in a row still both toast. A toast without an id falls back to the identity check. `composer test:toast` and `composer test:js` cover it.

## v0.4.18

### Upgrading

- **Warning and error toasts now stay until dismissed.** They used to close after 4 seconds (8 with an action). To keep the old timing, set `kit.toasts.durations` to `['success' => 4000, 'info' => 4000, 'warning' => 4000, 'error' => 4000]` in `config/kit.php`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f19d1c3a…9e8f` → `sha256:d35bf35a…1248`): `Kit/Feedback/Toaster.vue` and `toastPlacement.ts` gain the duration and away handling.

### Added

- **Toast durations per type:** `kit.toasts.durations` maps each type to milliseconds, or `null` to stay until dismissed. The server resolves it at flash time and sends it as `duration`. Defaults: success and info 4000, warning and error `null`. A timed toast with an action stays at least 8000. An unusable value falls back to the type's default.
- **Away rule:** a timed toast shown while the tab is hidden or the window is unfocused starts its timer only once the window is visible and focused again. Persistent toasts are unaffected.
- `composer test:toast` covers config resolution per type and the action rule; `composer test:js` covers the away logic.

## v0.4.17

### Fixed

- **Saving workspace settings confirms with a toast.** `SettingsController::update` flashed `status`, which nothing renders, so saving General gave no feedback. It now flashes `Toast::success('Workspace settings saved.')->from('save')`, which pulses the Save button. JSON responses are unchanged. `composer test:library` covers it.
- **Revoking an invitation confirms with a toast.** `PeopleInvitationController::destroy` redirected back with no feedback. It now flashes a plain `Toast::success('Invitation revoked.')`, with no `from`, since the row it came from is gone. JSON responses are unchanged.

## v0.4.16

### Fixed

- **Expected refusals are no longer reported.** Core already answered a `RuntimeException` whose message is a registered error code with that code's status, but the handler still reported it, so a refusal such as `bench_unavailable` (404) showed in error trackers as unhandled. A code registered with a status below 500 is now never reported. An unregistered message, or a code registered as 5xx, still is. `composer test:host-boot` covers it, and CI now runs that check.

## v0.4.15

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:73daee1d…3df9` → `sha256:03039592…65da`): `Workspace/Layout.vue` and `Partials/ShellDrawer.vue` gain the `drawer-nav` slot.

### Added

- **Shell `drawer-nav` slot:** `<Layout>` forwards it into the phone drawer, which renders it in place of the kit's pages (props `navigation` and `label`, as for `nav`). An app that uses `nav` can keep a page list in the drawer by passing `drawer-nav` too. Without it, nothing changes: the kit's pages, or none when `nav` is used. `composer test:shell` checks both cases.
- **Workspace-owned model concerns (workspaces).**
  - `Concerns\BelongsToWorkspace` scopes every query to the current workspace, and fails closed with `workspace_required` outside one. A new row takes the current workspace. Saving, moving or deleting another workspace's row throws `workspace_mismatch`.
  - `Concerns\GuardsStaleWrites` adds `updateIfCurrent($expected, $attributes)`: one conditional update on `lock_version` that bumps it, and throws `stale_write` on an old version without writing anything.
  - `stale_write` is registered as a 409 and as an MCP refusal code.
  - See `docs/models.md`. Covered by `composer test:workspace-models`.
  - No stub changed, so there is nothing to re-pin.

## v0.4.14

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:e39f0c88…aa00` → `sha256:73daee1d…3df9`): `Workspace/Layout.vue` and `Partials/ShellDrawer.vue` gain the `nav` slot.

### Added

- **Shell `nav` slot:** `<Layout>` takes a `nav` slot (props `navigation` and `label`) that replaces PrimaryNav at every width, rendered once, and the drawer then lists no pages. An app with its own module switcher can stop keeping its own `Layout.vue` (then `kit:update claim` it back). This is not the existing `bar` slot, which holds compact controls beside the nav. `composer test:shell` checks the stub.

### Fixed

- **`kit:update keep` reports a pending upstream change on the first plan.** Keep recorded the kit's current bytes as already seen, so a change that landed before the keep never showed: a `Layout.vue` kept since adoption planned `held: []` after the v0.4.10 search mount. Keep now records what the host last saw: the release a kept-since-adoption edit was made against, or an owned file's installed bytes. The next plan lists the change under `held` (with the stub to diff and the claim command), once. `composer test:adopt-in-place` covers it.
## v0.4.13

### Fixed

- **Global search ignores case on PostgreSQL.** The people provider matched with `like`, which PostgreSQL treats as case-sensitive, so "view" missed "Viewer" there (SQLite hid it). The new `SearchQuery::matches($builder, $columns)` ORs the columns through Laravel's case-insensitive `whereLike` (`ilike` on PostgreSQL), and `PeopleSearch` uses it. `docs/search.md` now tells providers to use it. No stub changed, so there is nothing to re-pin.

## v0.4.12

### Added

- **`kit:update keep <path>` and `kit:update claim <path>`:** a file the app keeps no longer blocks the lineage's updates. Before, a file kept since `kit:adopt` (an app's own `Layout.vue`) had no original in the lineage, so every later re-pin that changed it was a blocked conflict.
  - `keep` records the file as host-kept (`held` in the lineage record), releasing it if the lineage owned it. Later updates skip it and still apply the rest (new partials are written). Each plan lists, under `held`, an attention line naming what the kit changed upstream and the stub to diff against.
  - `claim` records the file's current bytes as the lineage's original, so later updates replace it while it's unchanged and conflict once it's edited.
  - Both are a reviewed plan, then an apply that writes no file.
  - `--lineage` is optional when only one adopted feature delivers the path.
  - `composer test:adopt-in-place` covers a kept file, keeping an owned edit, a re-pin that adds a partial, the attention, and claiming back. The lineage-record format is in `docs/catalog.md`.

## v0.4.11

### Upgrading

- After upgrading tey/mod, run `php artisan kit:update refresh-executor` once. An app with an explicit `kit.features` list gets each newly adopted feature added to it.

### Fixed

- **`kit:adopt` switches the feature on, as `kit:add` now does:** with an explicit `kit.features` list in `config/kit.php` (which overrides the lineages), an adopted feature stayed off. Both commands now add each adopted feature to that list, in canonical order. They edit only a literal list of quoted ids, verify by reading the file back, and undo otherwise; a computed list is left alone and named as attention. With `kit.features` null, nothing changes: the lineage switches the feature on. `composer test:install` and `test:adopt-in-place` cover it.
- **A stale `config/kit-executor.json` is named, not opaque:** after a tey/mod upgrade, every plan failed with "Executor provenance input must match the currently verified runtime". It now stops once with `executor_stale`, naming the file, the recorded and the running tey/mod, and the fix. The new `php artisan kit:update refresh-executor` rewrites only that file, and only when it holds a recorded runtime. `composer test:install` covers it.

## v0.4.10

### Upgrading

- Run `kit:update plan|apply` for `notes` (re-pinned `sha256:f174d87e…167a` → `sha256:640b093e…53d2`): the composer and the card editor are now Inertia `<Form>`s ending in `FormActions`.
- Run `kit:update plan|apply` for `core`, `shell` and `session-ui` (re-pinned). They add:
  - core: `Kit/Layout/StickyBar.vue`, `Kit/Layout/PageEnd.vue`, `Kit/Search/SearchBox.vue` and `Kit/Search/search.ts`, and update `index.ts` and `contract.ts`;
  - shell: `Workspace/Partials/GlobalSearch.vue`, and update `Workspace/Layout.vue`;
  - session-ui: `Workspace/Search/Page.vue`.
- Regenerate TypeScript types afterwards: `ShellData` gains `search`, and there are new `SearchViewModel` and `SearchResult` types.
- Global search is off until you switch on `workspace.search` in `kit.capabilities`. Until then nothing changes for anyone.

### Added

- **Global search (session-ui, opt-in):**
  - `Registry::searchProvider($key, $class, $ability, $label, $order)` registers a provider. Each one returns typed `SearchResult`s for the query in the current workspace, and is asked only for a viewer who holds its ability (through `Access`).
  - A provider that throws, or returns anything else, is skipped and logged without its message or the term. The others still answer, marked `partial`.
  - Surfaces: a results page at `/@{workspace}/search`, and a header field (`GlobalSearch`, ⌘K, Ctrl+K or `/`) with a live palette, in a sheet below `lg`.
  - A read-only MCP `workspace_search` tool returns the same results for the same person, with the new `search.read` scope.
  - Each person has a shared limit, `kit.search.per_minute` (30 by default): a 429 on the page and palette, `rate_limited` over MCP.
  - The kit ships one provider, `kit.people`: the workspace's members.
  - See `docs/search.md`. Covered by `composer test:search` and `test:search-js`.
- **StickyBar (core):** a full-width bar pinned to its container's foot until the end of the content. While pinned it registers with `useStickyBars`, so the Toaster and BulkBar stay clear of it. It is the page-controls sibling of FloatingToolbar, which stays the place for page actions.
- **PageEnd (core):** the end of a long page: Back to top, an optional jump list of sections, and `pagination` and `actions` slots. It shows only past one and a half screens, and hides under a pinned form bar.
### Fixed

- **Notes forms follow the page-form standard:** the composer and the card's editor were `useForm` with a raw `<form>`, so a host's form standard (every page form is Inertia's `<Form>` ending in `FormCard` or `FormActions`) failed. Both are now uncontrolled `<Form>`s with named controls and `FormActions`. The composer's files and intent key join the request through the form's `transform`, so a fresh intent key after a save doesn't read as an unsaved edit, and the reset composer is clean again (`set-defaults-on-success`). Proven in the starter with its form tests and a browser spec.

## v0.4.9

### Upgrading

- `notes` is a new opt-in feature: `php artisan kit:add notes`, then `migrate` and `kit:permissions:sync`. It creates `kit_notes` and `kit_note_intents` and never touches a `notes` or `app_notes` table you already have. To copy those, run `php artisan kit:notes:import --from=<table>` (see `docs/notes.md`).
- Re-pinned: base `sha256:9e6ee91d…cc75cf` → `sha256:c513b68d…f079` (`blueprint.json` names the notes migration block). New pin: `notes` `sha256:f174d87e…167a`. No other feature's pin moves.

### Added

- **Notes (`notes`):**
  - Workspace notes with an author, a colour, a pin, an optional subject and medialibrary attachments, and a stale-write guard and intent replay.
  - Three abilities: `notes.read`, `notes.write`, and `notes.moderate` for changing other people's notes. Moderation is its own permission, never implied by writing; the owner always has it.
  - One principal-first operation (`WorkspaceNotes`), used by the wall page (composer, colour filter, a ScrollList of cards with edit, pin and delete with a confirm, and a save toast), the `notes.recent` dashboard widget, and four MCP capability tools (`notes_list`, `notes_read`, `notes_write`, `notes_delete`).
  - Extension points: `Registry::noteSubject()` (notes about an app's records; visibility follows the record) and `Registry::noteComposer()` (app-side composer options and a record search).
  - `kit:notes:import --from=<table>` copies a legacy notes table idempotently, reports what it skipped, and never changes the source.
  - `composer test:notes` covers it. See `docs/notes.md`.
- **`legacy_table` host step:** a feature can name a table an earlier release or the app kept its data in. When the table exists, `kit:add` and `kit:adopt` show it as attention that doesn't block, with the step's `why` (the notes import). `kit:adopt <feature> plan` now prints a feature's unmet host steps as attention too.

## v0.4.8

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:59b0fc72…74c1` → `sha256:20c52d96…69f8`): it adds `resources/js/pages/Workspace/Dashboard/Partials/` and updates the dashboard page. Regenerate TypeScript types afterwards.
- The dashboard now shows the kit's three widgets (You, Recent activity, People). To keep fewer, set `kit.dashboard.widgets` in `config/kit.php` (for example `['you']`, or `[]` for none).
- Run `kit:update plan|apply` for `core` (re-pinned): it adds `Kit/Table/IndexToolbar.vue`, `Kit/Table/BulkBar.vue` and `Kit/Table/selection.ts`, and changes `DataTable.vue`, `index.ts` and `contract.ts`.
- **An app that overrode the dashboard controller to inject its own widgets** (noted after the release): from this version `DashboardController` renders the kit's Registry widgets itself and returns `toResponse()`, so an override that added its own widget props, or wrapped the response, no longer works. Register the app's widgets with `Registry::widget()` instead, or set `kit.dashboard.widgets` to `[]` to drop the kit's own. See [docs/dashboard.md](docs/dashboard.md#upgrading-an-app-with-its-own-dashboard-widgets).

### Added

- **Dashboard widgets (session-ui):** `Registry::widget($key, $class, $order, $ability)` adds a card to the dashboard, and `Registry::widgets()` lists them in order. A widget extends `Dashboard\Widget`, a typed ViewModel with a frame (`title`, `component`, `span`, `state`, `empty`, `error`, `actions`) and its own props. Each widget is gated by its ability through `Access` (so its capability, entitlement and permission apply), and one that throws becomes an error card while the dashboard still answers 200. `DashboardViewModel` gains `widgets`. The kit ships `kit.you`, `kit.activity` (behind `members.manage`) and `kit.people` (a ScrollList). The page renders them in a responsive, span-aware grid (`WidgetGrid`, `WidgetCard`), and an app's widget body lives in `resources/js/widgets/`. With mcp adopted, the read-only `workspace_dashboard` tool returns the same frames, and session-ui declares the narrow token scope `dashboard.read` (now grantable to a connection). See `docs/dashboard.md`. `composer test:dashboard` covers it; there is no browser test.
- **IndexToolbar:** the DataTable's toolbar is now its own component: search plus one choice per filter the server declares with a label and options (`TableQuery::filter($name, $apply, $label, $options)`, sent as TableData `choices`). A value outside a filter's options is a 400 (`InvalidFilterValue`), like an undeclared filter.
- **Bulk actions and selection:** `TableQuery::bulk(Action)` makes the rows selectable (a checkbox column and a page checkbox, `selection.ts`). While rows are selected, `BulkBar` floats at the viewport's foot (a FloatingToolbar, so it registers with useStickyBars) with the count, the actions and Clear; an action posts `ids[]`, after its `confirm`. The selection survives pages, sorts and filters until it is cleared or acted on.
- **`TableSelection::from($request, $scope)`:** resolves a bulk action's `ids[]` inside the handler's own scope and fails closed: no ids, more than 100, a malformed id or any id outside the scope refuses the whole request (422).

## v0.4.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned).

### Added

- **`kit:toast` event:** the Toaster dispatches a `kit:toast` window event with the toast as `detail` whenever it shows one, so an app can keep its own history (a notification bell) of server-flashed toasts.

### Fixed

- **Form saves are a toast trigger:** `FormActions` renders `data-action` on its submit button (the submit descriptor's `key`, else `save`), so `->from('save')` pulses the Save button instead of falling back to a plain toast.

## v0.4.6

### Changed

- **Saves pulse by default:** a `success` toast without an explicit `style()` is now `pulse` (a shell toast plus a brief ring on the triggering button) instead of `fly-out`. Fly-out stays available with `->style('fly-out')`. Server-side only; no stub changed, so no `kit:update` is needed.

## v0.4.5

### Upgrading

- Run `kit:update plan|apply` for `core`, `shell` and `session-ui` (all three are re-pinned), then `npm install`: core now requires `vue-sonner` and declares `@inertiajs/core`.
- If your app renders its own toaster from the `toast` flash, remove it: the kit shell now mounts one. A FocusShell or custom layout mounts `<Toaster />` itself.

### Added

- **Headless toasts:** `Tey\Kit\Features\Core\Toast` and the `Toastable` concern flash Inertia's `toast`; the controller decides, the frontend only renders. One non-destructive action at most (8s with an action, 4s without). See `docs/toasts.md` for when a controller toasts.
- **Anchored toasts:** `->from('<action id>')` links a toast to the descriptor button that triggered it, with `->style('fly-out'|'anchored'|'pulse')`; fly-out is the default for saves. Falls back to a plain toast when the trigger is gone; `prefers-reduced-motion` gets no flight.
- **Shell-level placement:** `kit.toasts.position` is `auto`, `top-center`, `top-right`, `bottom-right` or `bottom-center`. `auto` picks bottom-center on phones, top-center when sticky bars and right-aligned header actions are present, top-right with sticky bars only, else bottom-right.
- **Sticky bar registry:** `useStickyBars()`; `FloatingToolbar` registers, and toasts keep clear of registered bars.

### Fixed

- The People settings page reads role labels from the server-provided cell value, so it passes tey/standards' descriptors rule.

### Known limitations

- The fly-out and anchored animations are covered by unit tests only; the kit has no browser harness yet.

## v0.4.4

### Fixed

- **`WorkspaceUrl` resolves an id over MCP:** `WorkspaceUrl::slug()` (and so `to()`, `full()`, `shareable()`) read only the web guard, so a bearer-authenticated MCP call (Passport) saw no one and refused a member's workspace id as `workspace_not_found`. It now falls back to the request's authenticated user, still scoped to that person's active memberships; a non-member and a guest are still refused. Web and console behaviour is unchanged.

## v0.4.3

### Added

- **Per-call destructive:** `CapabilityTool::destructive(array $validated): bool` is overridable and defaults to the tool-level flag, and `ConsentGate::confirm()` takes it, so one tool can confirm only some calls. Keep the tool-level flag for the MCP `destructiveHint`.
- **`notFoundCode()`:** an overridable `CapabilityTool::notFoundCode(): string` (default `not_found`) names the code an uncoded `ModelNotFoundException` is refused with, for example `workspace_not_found`. `ConsentGate::refusal()` takes it as its second argument.

### Fixed

- **`operation_failed` is reported:** an unexpected exception that `ConsentGate::refusal()` maps to `operation_failed` is now passed to `report()`, so the host's exception handler logs it. Coded refusals, validation and not-found are not reported.
- **Control arguments no longer reach `call()` (behaviour fix):** `intent_key` and `confirm_token` are stripped from the arguments passed to `call()`, `summary()` and `ToolCalled`; they stay available to the gate. A host that handed arguments whole to an operation no longer needs to strip them.

## v0.4.2

### Added

- **Host MCP refusal codes:** `Registry::mcpRefusalCodes([...])` registers a host's domain codes (`stale_write`, `report_blocked`), which a `CapabilityTool` refusal now passes through as they are, alongside `ConsentGate::CODES`. Unregistered codes are still `operation_failed`.
- **Validation detail:** `validation_failed` carries the field errors as structured content, `{error: 'validation_failed', errors: {field: [messages]}}`. The error text is still the bare code; `ConsentGate::refusal()` returns an array that may carry `errors`.
- **`Tey\Kit\Features\Mcp\Events\ToolCalled`:** dispatched once after a `CapabilityTool`'s `call()` succeeds, with the tool name, class, principal, workspace id, validated arguments and result, inside the intent's transaction. Not dispatched on a refusal, `confirmation_required` or an intent replay.

### Fixed

- **Coded refusals win over `not_found`:** a `RuntimeException` whose message is a known code, including a `ModelNotFoundException` subclass carrying `workspace_not_found`, passes through instead of becoming `not_found`. v0.4.0 checked `ModelNotFoundException` first; v0.4.1 already reordered it, and a regression check now pins the order.

## v0.4.1

### Upgrading

- Run `kit:update` for `core` (the changed `Layout/splitState.ts`) and `mcp`. If your app commits a route snapshot, update it: the `oauth/authorize` routes gain the `RefuseCookieSessions` middleware.
- Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment.

### Fixed

- **MCP consent on cookie sessions (#108):** Passport keeps the consent request in the session, which outgrows a cookie, so consent failed silently on `SESSION_DRIVER=cookie`. The consent routes now refuse there with a 503 page naming the fix and an error log line. A new `config` host step kind (`{key, not, why}`, attention only) lets the mcp plan flag a cookie session driver. Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment. Re-pins `mcp`.
- **`kit:adopt <feature> plan` exit code (#114):** a blocked plan exits non-zero with its output unchanged. This shipped in v0.4.0; v0.4.1 adds no change beyond the check that covers it.
- **SplitPane storage import (#117):** `splitState` imports Inertia's `http` statically, so Vite no longer warns INEFFECTIVE_DYNAMIC_IMPORT on every build. The localStorage fallback is unchanged. Re-pins `core`.

## v0.4.0

### Upgrading

- **Behaviour change for hand-written gated tools:** a tool using `GatedByCapability` is now refused by its `ABILITY` before its operation runs. When the person's roles do not allow the `ABILITY`, the call returns `permission_denied` and `handle()` is not reached, so an operation that refused the same call with its own error code no longer gets to. This is intended. A host that relied on its own code for that case should check for `permission_denied` instead.
- **Regenerate TypeScript types** after updating with the host's typescript-transformer command, so the kit's changed view models and props reach the frontend.
- **Migration needed for `mcp`:** run `php artisan kit:update plan --lineage=mcp`, then `apply`, then `php artisan migrate`. This adds `database/migrations/kit/mcp/0001_01_02_000601_create_kit_mcp_intents_table.php` (the `kit_mcp_intents` table). Until it is migrated, a tool call carrying `intent_key` fails as `operation_failed`. Calls without one are unaffected. Then schedule `model:prune` for `Tey\Kit\Features\Mcp\Models\McpIntent` daily, or expired intents are never deleted (the snippet is in `docs/mcp-tools.md`). Set `kit.mcp.intent_ttl` in `config/kit.php` to change the 24-hour lifetime.
- **Connections (#86) need no migration.** Existing connections keep the narrow scopes they were approved with and behave as before; nothing re-prompts. To give an assistant acts-as-me, the person approves `mcp:act` for the workspace; an assistant whose token asked for narrow scopes must reconnect without them first. The Passport consent screen's wording for `mcp:use` changes to "Act as you in the workspaces you approve…". An app whose own MCP tools caught `RuntimeException` and called `Context::principal()` keeps working; moving them to `ConsentGate` adds the confirmation and the refusal mapping. Intent replay for create/copy tools is not in this release.

- **Apps installed before feature lineages** adopt each feature first with `kit:adopt <feature> plan|apply`, in the order `kit:update` prints, then update. `kit:adopt` plans also run the existing-table check and print their apply command.
- **The `mcp:use` scope label changed** to "Act as you in the workspaces you approve…", and `mcp:act` was added. Update any test that asserts the scope labels, and regenerate any committed MCP capabilities file.
- **Optional:** register `Tey\Kit\TypeScript\ExplanationTypesExtension` in the typescript-transformer config, so page props carrying an Explanation are typed instead of `undefined`.

### Added

- Plans spell out what the host must do beyond the files the kit writes (#8).
  - **What `kit:add` and `kit:update plan` report:**
    - the features' host steps;
    - their npm requirements, checked against the host's `package.json` and `node_modules`;
    - their optional integrations;
    - per feature, which migrations are new, changed or already there.
  - **New `host_steps` kinds:**
    - `env`, checked against `.env.example` only: the kit never reads `.env`. An optional `or_file` also meets it when that file exists; the kit checks existence only and never reads it;
    - `provider`, checked against the registered providers;
    - `schedule`, checked against the scheduled events.
  - **New `optional` list:** integrations a host may add, printed as manual steps.
  - **What blocks:** only `user_model` still blocks a plan. Every other unmet step, and an npm requirement that's missing, outdated or unverifiable, keeps the plan executable, opens the text preview with an ATTENTION block and sets `attention` and `warnings` in JSON.
  - **JSON:** the output is additive: `host_steps`, `unmet`, `missing_packages` and `changed_migrations` keep their shape.
  - **Plan identity:** a `kit:add` plan identity now binds the step statuses and each npm requirement's constraint, declared range and status, but not the installed version.
  - **Declared steps:**
    - mcp: `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` (env; each is also met by its `storage/oauth-*.key` from `passport:keys`), and `model:prune --model=Tey\Kit\Features\Mcp\Models\McpIntent` (schedule);
    - core: `ExplanationTypesExtension` as an optional integration.
  - **Check:** `composer test:plan-steps` covers it and runs in CI.
  - Re-pinned: package `core` `sha256:7aa65789…daae5` → `sha256:d3f0c696…3ebfc`, `mcp` `sha256:9077a379…aa9a` → `sha256:d2135421…bc07` (manifests only; no stub changed).

- `Registry::navigation()` and `modulePage()` take `active` as a list of path patterns, and an entry `route:<name pattern>` matches the current route's name (#93). A module that spans several route prefixes, or one found by route name (`route:trips.entries.*`), lists them all; a link is current when any entry matches. A single string stays valid, and an empty entry still means the workspace root only. An empty list or a bare `route:` refuses at registration. The match is `PageShell::matches()`, covered by `composer test:shell` and `test:library`. No stub changed, so no pin moves.

- `Tey\Kit\TypeScript\ExplanationTypesExtension` types a page's Explanation props as the kit's own (#83). A view model with a `list<ExplanationNode>` or an `ExplanationDocument` prop was emitted by typescript-transformer as `undefined[]` or `undefined`, so the page cast it. Register the extension in the host's transformer config (`$config->extension(new ExplanationTypesExtension)`) and those props become `import('@kit/ui').ExplanationNode[]` and `import('@kit/ui').ExplanationDocument`. It maps every Explanation data class and enum to the type `explanation.generated.ts` exports. `composer test:typescript` generates types for a fixture view model, proves the gap without the extension and the fix with it, and fails if the mapped names drift from the generated file; it runs in CI. `spatie/laravel-typescript-transformer` is now a dev dependency (hosts install it already). No stub changed, so no pin moves.

- `Popover` is exported from `@kit/ui` (#82): contextual detail anchored to a trigger, on Reka's Popover and never modal on desktop. Props `title` or `label` (one names the panel; with neither the trigger does), `side`, `align` (`start`), `offset` (8), `width` (`auto`, `sm`, `md`, `lg`, `trigger`), `closeButton`, `collisionPadding`, `collisionBoundary` and `sheet`; slots `trigger`, `anchor`, `title`, `default` and `footer`; `v-model:open` and a `dismiss` event (`escape`, `outside`, `close`, `navigate`). Collision handling is Reka's alone, so it no longer measures a host shell element. An Inertia navigation closes it, Tab past the last control closes it and moves on, and Popovers nest through Reka's dismissable layers. Below 640px it renders in `Modal` as a bottom sheet, through Modal's public props; it will move onto the shared modal base when that exists. The close button's name is `popover.close` in the kit `ui` lang file ("Close"), overridable per use with `messages`. `composer test:popover` covers the helpers. `ComputedValue`'s overlay is a consumer (width `lg`, close label "Close working") and behaves as before. Re-pinned: package `core` `sha256:0f1835ad…7c2b` → `sha256:0f2545ad…3ff2`.
- MCP tools declared by capability (#85). `#[Capability('<capability>', destructive: false, scope: null)]` names the workspace capability a laravel/mcp tool belongs to, and `Registry::capabilityTools([...])` registers it.
  - A registered tool is absent from the MCP list while its capability is off or out of plan. A call to it anyway is refused as `capability_unavailable` / `entitlement_unavailable` instead of "tool not found".
  - `capabilityTools()` fails closed at boot: no attribute, no `GatedByCapability`, an undeclared capability, no `ABILITY` or `scope` to admit the call under, an `ABILITY` another capability gates, or no `mcp` feature.
  - `CapabilityTool` is a base class for one principal-first operation. It admits the call through `ConsentGate`, refuses unknown arguments, validates, asks for confirmation and maps refusals to codes. Its name defaults to the capability with `.` as `_`.
  - `GatedByCapability` reads the attribute before `ABILITY`. A `CAPABILITY` constant is never read: hosts use that name for their own MCP tool ids.
  - A tool's `ABILITY` stays its permission: the tool is also hidden from a person whose role lacks it, and refused as `permission_denied`. This applies to the kit's own settings tools too, so `workspace_settings_update` is no longer listed for a member who cannot update settings. `Access::permits()` is the role rule `authorize()` uses.
  - `composer test:capability-guard`, `test:entitlements` and the new `test:capability-tool` cover attribute-registered tools and the base class's main path.
  - See `docs/mcp-tools.md`, which includes moving a hand-written tool onto the attribute. No stub changed.
- **MCP consent: one "acts as me" grant, optional narrow grants, and in-chat confirmation (#86).** The new `mcp:act` scope is the default grant: a connection that holds it may call any tool, and Access's role, policy, capability and entitlement checks decide each call, as in the app, so a new tool never needs re-approval. The per-ability scopes (`settings.read`, …) stay as optional narrow grants and only cover their own ability. Assistants request the one scope laravel/mcp advertises, `mcp:use`, so a token with no narrow scope acts as the person (the consent screen now says so), and a token that asked for narrow scopes is held to them. Both the token and the connection must cover the ability, or the call is refused with `connection_denied`.
- `Tey\Kit\Features\Mcp\ConsentGate`, the one gate MCP tools go through: `admit($ability)` returns the delegated principal and the bound workspace; `confirm($tool, $principal, $request, $summary)` is the two-step for destructive tools (a tool is destructive when its `#[Capability]` says so, else by laravel/mcp's `#[IsDestructive]`): the first call returns `confirmation_required` with a single-use `confirm_token` bound to the person, the connection, the tool and the exact arguments (5 minutes, in the cache); the same call carrying it runs, and a reused, expired, forged or mismatched token is `confirmation_invalid`; `refusal($e)` passes known codes through, maps validation to `validation_failed` and a missing model to `not_found`, and hides anything else as `operation_failed`. The settings tools use it.
- Audit entries made through an MCP connection carry `via: {kind: assistant, connection_id, client}`, so they read as "<person> via <assistant>". A `via` the caller already put in the details is kept as given.
- `composer test:mcp-consent` covers the grants, the confirmation, the refusal codes and the audit tag on SQLite memory with real Passport tables, and runs in CI with `composer test:mcp-refresh`, which now runs there too.
- **MCP intent replay** (follow-up to #86). A `CapabilityTool` whose `rules()` accept `intent_key` stores each call's result under it. A retry with the same person, connection, `intent_key`, tool and arguments replays the stored result instead of running again, including a destructive call that was already confirmed, which is not asked again. The same key with other arguments or another tool is `intent_conflict`. A call without `intent_key` runs every time, and a call that fails stores nothing. The operation and its stored row commit in one transaction, so two racing calls with one key run the operation once: the second's work rolls back and it returns the first's result. The new `ConsentGate::recall()` and `remember()` do this for a hand-written tool. The results live in the new `kit_mcp_intents` table for `kit.mcp.intent_ttl` seconds (24 hours by default). After that a key is treated as new, and `model:prune` on `McpIntent` deletes expired rows; see `docs/mcp-tools.md`. `composer test:capability-tool` covers it. Re-pinned: package `mcp` `sha256:f507a2bb…117b` → `sha256:9077a379…aa9a`.

### Changed

- `php bin/catalog-pins.php` prints each catalog feature's current identity (and the base's) next to its pin in `src/Catalog.php`, marked `ok` or `changed`, so re-pinning after a stub change no longer means reading `Blueprint` internals. `--check` exits 1 while a pin is stale, and runs in CI.
- CI runs every `composer test:*` script. `test:tables`, `test:lists`, `test:explanation`, `test:input-select`, `test:js` and `test:split-drop` existed but were never run there. `php bin/ci-scripts-check.php`, itself a CI step, fails when a test script is in neither `tests.yml` nor its `NOT_IN_CI` list (script => reason; empty today).
- Core states `reka-ui` `>=2.11.0` in `requires.npm` (#84). `InputSelect` needs 2.11.0 or later to typecheck and relies on its non-modal behaviour. Still informational: `kit:add` does not install it, so the host's `package.json` must carry it. Re-pinned: package `core` `sha256:c0635fd3…f65a` → `sha256:0f1835ad…7c2b`.

### Fixed

- `Audit::write()` keeps a `via` the caller supplied. Through an MCP connection it overwrote any host `details['via']` (for example `'assistant'`) with the kit's `{kind, connection_id, client}`, so the host's value was lost. The kit now writes its `via` only when the details have none. `composer test:mcp-consent` covers both.
- The `ComputedValue` overlay trigger has `aria-controls` again. The fix in this release still bound `id` and `aria-controls` as `undefined`, which won over Reka's, so the trigger pointed at nothing. `ComputedValueRoot` now exposes `popoverTriggerAttrs`, the trigger attributes without `id`, `aria-controls` and the click handler, and the Popover trigger binds that, so Reka's `aria-controls` points at the panel. `composer test:explanation` checks the source; the browser behaviour is proven in a host. Re-pinned: package `core` `sha256:6c4487fb…342f` → `sha256:93462cac…6fbe`.
- `kit:adopt <feature> plan`, the path a pre-lifecycle host takes:
  - a new migration creating a table the host database already has makes the plan not executable and is listed in `unmet`, as in `kit:add` and `kit:update plan`, including the skip for a migration the host already ran;
  - the plan prints the full apply command (`kit:adopt <feature> apply --intent=… --exclusive-checkout --expected-plan=…`) above its JSON and carries it as `apply`;
  - `composer test:plan-steps` covers both.
- The `kit:add` ATTENTION banner said "Nothing here blocks the apply" above a plan that was not executable. It now says the plan is not executable when it is not. `composer test:plan-steps` covers both banners.
- The plan's existing-table check no longer blocks a migration the host already ran. Re-adding or restoring a kit migration whose tables exist because that same migration was applied before is skipped by `migrate`, so it is not a collision. The check reads the migrations table; when it cannot, it checks as before.

- MCP tools are gated by the `#[Capability]` attribute only. `Capability::of()` and `GatedByCapability` also read a tool's `CAPABILITY` constant, so a host whose hand-written tools keep their MCP tool id there (`assignments.list`) had every such tool hidden and refused as `capability_unavailable`. The constant fallback is gone: a tool without the attribute is gated by its `ABILITY` as before 0.4, and `CapabilityTool`'s default name comes from the attribute alone. A tool moving onto the attribute adds it (see `docs/mcp-tools.md`). `composer test:capability-tool` covers a tool whose constant names an undeclared id.
- The MCP intents table is `kit_mcp_intents`, kit-prefixed like the kit's other tables, so it no longer collides with a host's own `mcp_intents` (migrate failed with 42P07). The migration is unreleased, so it is changed in place. Re-pinned: package `mcp` `sha256:d2135421…bc07` → `sha256:e1bfc73a…ac43`.
- A plan whose new migration creates a table the host database already has is not executable: `kit:add` and `kit:update plan` list it in `unmet`, naming the table and the migration, instead of leaving migrate to fail. The table is read from `Schema::create('<name>'` in the migration and checked with `Schema::hasTable`; a database the kit cannot reach is not checked.
- Plan output:
  - the `kit:add` text plan prints the exact apply command, with `--expected-plan` and `--intent`; `kit:update plan` adds `apply`, the exact command with `--expected-plan`, `--exclusive-checkout` and `--intent`;
  - a `schedule` step names `routes/console.php or bootstrap/app.php (withSchedule)`;
  - `kit:update plan --lineage=<feature>` whose requirement has an update still pending lists it in `depends_on` and gives the order in `order`.
  - `composer test:plan-steps` covers each.

- The desktop `Popover` has an accessible name and takes focus on open. Reka 2.11 merges `aria-labelledby = triggerId` after forwarded props, so the kit's heading id never applied; the panel is now named after mount (by its heading, else `label`, else the trigger), which Reka does not patch again. Open auto-focus moves focus to the panel (`tabindex="-1"`) on the next tick, found by its element rather than the content ref, whose `$el` left focus on the trigger. `ComputedValue` no longer overrides Reka's trigger `id` and `aria-controls` in its Popover, so `aria-controls` points at the panel. Restored for hosts, both removed in #82: the `data-kit-popover` attribute on the desktop panel, and `overlayKey` from `Explanation/scope`, now a deprecated alias of `sheetKey` (removed in 0.5). `composer test:explanation` checks the source; the kit has no browser test, and the browser behaviour is proven in a host. Core re-pinned: `sha256:d3f0c696…bfc` → `sha256:6c4487fb…342f`.

- The MCP authorization screen shows that it is working: Authorize and Deny each show a busy state on submit (the button is disabled, with a spinner, "Connecting…" or "Cancelling…", and `aria-busy`), a second submit is refused, and both reset when the browser restores the page from the back-forward cache. The approve and deny posts end in a redirect to the external client, so the page gets no response to react to. The view is package code, so no pin moves and no `kit:update` is needed. `composer test:mcp-consent` checks the view's source; there is no browser test.

- A `SplitPane` aside never has a scrollbar of its own (#105). With `stickyAside` it used a fixed `100dvh` height and an inner `PaneScroll`, so the page and the aside each scrolled. Now it is `sticky` only while its content fits the viewport below the shell header (measured by a ResizeObserver and the window resize, in the new `Layout/useStickyWhenFits.ts`, fit rule in `Layout/stickyFit.ts`); taller, it sits in the flow and scrolls with the page. `mainScroll` and the phone `sheet` are unchanged. Audit of every stub for an aside or sidebar with its own `overflow-y-auto` and a viewport height: only this one; `SettingsFrame`'s sidebar and the shell header have none. `composer test:js` checks the fit rule and that the aside source has no fixed viewport height or inner overflow. Core re-pinned : package `core` `sha256:6d8a05fb…b1db` → `sha256:7aa65789…daae5`.

- The Modal's `role="dialog"` element is named by the modal's title (follow-up to #82). The package renders that element unlabelled; `ModalPanel` now sets `aria-labelledby` on it, so every Modal, drawer and the Popover's phone sheet is a named dialog. Re-pinned: package `core` `sha256:ee389558…7791` → `sha256:6d8a05fb…b1db`.

- The Popover's phone sheet starts focus on the top of its content again (follow-up to #82). `Modal` takes `initialFocus` (`field`, the default, or `body`); `body` focuses the scrolled-to-top body instead of the first field or button, so a text-only sheet no longer leaves focus on the package's Close button. The Popover passes `body`. Re-pinned: package `core` `sha256:0f2545ad…3ff2` → `sha256:ee389558…7791`.

## v0.3.2

### Fixed

- The dashboard greeting follows the workspace's timezone until the browser has sent its own (#87). With no `tz` cookie and no zone saved for the person, `Good morning, …` used the UTC hour even when the workspace had a timezone set; `DashboardController` now passes the workspace's zone as the fallback (UTC when Settings isn't installed). `composer test:greeting` covers it over HTTP with a frozen clock and runs in CI.
- `DataTable` shows its row count on a single page (#88). The footer's `1–5 of 5` line showed only past one page; it now shows whenever there are rows, and only the page links wait for a second page. `composer test:table-count` checks the stub's template and runs in CI. Re-pinned: package `core` `sha256:42c9c901…ddd9` → `sha256:c0635fd3…f65a` (`kit:update` delivers the fixed stub).

## v0.3.1

### Fixed

- `contract.ts` in v0.3.0 does not parse: the SplitPane props comment lost its opening `/**`, so a host's `vue-tsc` fails on the generated `resources/js/components/Kit/contract.ts`. The comment is restored. `composer test:stubs` now parses every TypeScript stub with Node's type stripper and runs in CI. Re-pinned: package `core` `sha256:4ac9554d…1d1a` → `sha256:42c9c901…ddd9` (`kit:update` delivers the fixed stub).

## v0.3.0

### Upgrading

- BulkDrop's DOM hooks are renamed. A host that copied the earlier dropzone and selects on its attributes (tests, styles, scripts) moves from the `data-capture-*` hooks to `data-dropzone` (the surface), `data-dropzone-overlay`, `data-dropzone-prompt`, `data-dropzone-hint`, `data-dropzone-browse` and `data-dropzone-input`, plus `data-dragging` while a drag is over it. `data-paste-target` is removed and not kept as an alias: paste is handled on the focusable `data-dropzone` surface itself, so focus that element and paste (a page-level paste adapter is not part of the kit).
- **Requires tey/mod ^0.11 and tey/concierge ^0.8.2.** Update with `composer require tey/kit tey/mod:^0.11 tey/concierge:^0.8.2 -W` (the `-W` lets Composer move tey/mod past a locked 0.9 or 0.10).
- **The shell moves out of session-ui.** An app that installed session-ui before 0.3 moves the shell's files onto the new `shell` feature with `kit:update`, one reviewed run per step: `kit:update plan --lineage=session-ui` (releases `pages/Workspace/Layout.vue` and `Partials/` at an interim version), then `--lineage=shell` (installs shell at exactly those bytes, with no write), then `--lineage=session-ui` again to finish, and `--lineage=shell` once more to take shell's own changes. Each run prints the next. An edited moved file stops the first run before any write and prints its diff; edit it back or keep it as the app's. Don't retry the first session-ui run after shell has taken the files: on tey/mod 0.11.0 that retry fails recovery (`Third file value blocks maintenance recovery.`); the fix ships in tey/mod 0.11.1.
- **Replace the user menu and workspace switcher with slots, not aliases (#59).** The shell's `user-menu` and `workspace-switcher` slots default to the kit's partials with the same props. An app that shadowed `./Partials/UserMenu.vue` or `WorkspaceSwitcher.vue` with Vite aliases can fill the slot instead and drop the aliases.
- **`resources/css/kit.css` changed** (the gutter rule, see Fixed). Run `kit:update` to take it; an app that edited the file keeps its edits through the usual three-way plan.
- **Migrate for entitlements:** the `access` feature has a new migration, `kit_entitlements`. Run `kit:update` for `access`, then `php artisan migrate`. Nothing is gated until a feature declares an entitlement, so existing workspaces are unchanged. The old `entitlements` table and `Access::authorize(..., paid: true)` still work for now; move a "paid" check to an entitlement on the capability it gates.
- **People is absent while `workspace.settings` is off.** Settings › People, its modals and its writes now return 404 when the capability behind `members.manage` is off for the workspace or person, like every other surface of an off capability. Before, the section was hidden but the page still opened for anyone holding `workspace.members.manage`.
- **Apps installed before feature lineages** can now update. `kit:update` on such an app refuses and prints the commands to run first. Adopt each feature in place, in the order printed: `php artisan kit:adopt <feature> plan`, then `kit:adopt <feature> apply --intent=<name> --exclusive-checkout --expected-plan=<plan>`. After that, update each feature as an adopted app does: `kit:update plan|apply --lineage=<feature>`. This replaces hand-copying release changes, such as the core `0001_01_02_000100` migration change in v0.2.0.
- **Changed migrations need review:** when a `kit:update` plan would replace a file under `database/migrations/`, the plan lists it in `changed_migrations` with a host step. The apply is refused until you pass `--accept-changed-migrations`. A migration that already ran is not run again, so for each one add a new migration that makes the same schema change.
- A staging deploy now shows the indicator by default. Set `kit.environment_indicator` to `[]` to turn it off, or add an entry for another environment name (`'uat' => ['label' => 'UAT', 'colour' => '#0e7490']`). `session-ui`'s `Workspace/Layout.vue` changed: run `kit:update`.
- Nothing changes for an app that sets none of the new keys. `session-ui`'s `Workspace/Layout.vue` changed (dark logomark and lockup): run `kit:update`. A host that overrides the header's mark through the `mark` slot is unaffected.

### Added

- `Tey\Kit\Mail\FluentMailMessage`: the fluent mail composer (all mail = a Notification composed in PHP as ordered blocks, never a template). Escaped by default; `markdown()`, `markdownLines()` and `markdownView()` are the explicit raw escape hatches; `preheader()`, `headline()`, `facts()`, `table()`, `panel()`, `callout()`, `note()`, `bullets()`, `quote()`, `divider()`, `button()` and `section()` compose the body, and stock `greeting()`, `line()` and `action()` land in order. It needs no service provider: the constructor registers the `kit-fluent-mail` view namespace and appends `resources/views/fluent-mail` to `mail.markdown.paths` (the host's paths keep priority), so it renders through the kit's branded layout when `mail.brand` is applied and Laravel's layout otherwise. `Tey\Kit\Mail\MailConformance` scans a directory for a Mailable, the Mail facade or a `toMail` that skips the composer; `docs/mail.md` shows the host's architecture test. `composer test:mail` covers rendering, the text part, macros and the scanner; run in CI. No change to a feature pin. Graduated from a client app.
- A `shell` feature that needs only core (the Shell spec). It owns the generated `pages/Workspace/Layout.vue` and `Partials/`, which move out of session-ui unchanged: every selection with session-ui generates the same files with the same bytes, and session-ui now requires shell, so `kit:add session-ui` adds it. A host with its own tenancy adopts `core` and `shell` without Concierge, Pennant or Fortify. A selection made before the split (a session-ui lineage or a `kit.features` list without shell) boots with shell carried. Mod keeps a lineage's files, so a host that already installed session-ui cannot hand them to shell: `kit:update --lineage=session-ui` refuses and names the files and their new owner, with nothing written. Pinned: package `shell` `sha256:1b05c57a…9f97` (new); re-pinned `session-ui` `sha256:e4181ca3…df85` → `sha256:0e9134ce…3bdc` (`kit:update` delivers the move).
- ShellData and ShellKind move to `Tey\Kit\Features\Shell\Data`. The `SessionUi\Data` names stay as PHP class aliases, and shell generates `resources/js/types/kit-shell.d.ts` aliasing the old TypeScript names.
- The shell's `bar` slot (the Shell spec): a control that stays in the bar at every width, such as a search opener. It renders once and never in the drawer, so it replaces teleporting into the shell's markup.
- SubNav (the SubNav spec): `ShellData::$subNavigation` lists the active module's pages as a strip of links under the header, with the server's current page, sideways scrolling on a phone and no tab roles. Features register pages with `Registry::modulePage()`; `PageShell` fills it with the pages the viewer may open.
- GuestShell (the Shell spec, GuestShell section): the guest/auth layout, in the core-only shell feature. It has the bar's lockup (the new shared `Partials/Wordmark.vue`), a card, a narrow or wide measure, a title and description, a labelled way back from the host and the appearance switch. It needs no ShellData, user or auth route.
- Capability guard (#54). `composer test:capability-guard` walks every capability in the Registry, kit and app alike. It switches each one off for a workspace and checks that every page from its navigation links, settings sections and placeholders is a 404. It checks that its links, sections and MCP tools leave the shared shell, page props, settings listings, the capability listing and the MCP tool list, and that its abilities are refused as `capability_unavailable`. Switched back on, everything returns. A capability with nothing the guard can see fails it. The kit has no dashboard-widget, command-palette or per-feature action-descriptor registry yet, so those are not checked, and a route served without a navigation link, settings section or placeholder is not discovered. Runs in CI.
- Page actions: `FloatingToolbar` (with `FloatingToolbarDot` and the `provideSurface` / `useSurface` surface context), `ActionBar` (with `ActionLabel`, `layoutActions`, `placeActions` and `isNavigation`) and `SectionHeader`, the one header for sections, widgets, cards and panes (built to the FloatingToolbar, ActionBar and SectionHeader specs). FloatingToolbar takes its actions and status through its slot and reads no store. `Button` takes the pill shape and inverse tone inside a FloatingToolbar and is unchanged elsewhere. `Section` renders its title through SectionHeader; its props are unchanged. `Tey\Kit\Features\Core\Data\Action` gains optional `intent`, `group`, `menu`, `icon` and `newTab` (defaults keep existing payloads valid). `composer test:js` runs the placement and number tests. Graduated from a client app. Re-pinned: package `core` `sha256:234e79df…38b4` → `sha256:d82d47dd…966f` (new stubs; `kit:update` delivers them).
- Page-action follow-ups (spec amendments). FloatingToolbar's slot is ActionBar and status only (every action is a descriptor, so it overflows into the ⌄ menu on phones); a button or link slotted directly warns in development. `ButtonGroup` now takes its look from `paper` and neutral outline tokens in `kit.css` (`bg-paper`, `ring-outline`, `text-outline-foreground`, `text-outline-muted`, `bg-outline-wash`), defaulting to its old gray and white values so the default theme is unchanged; a theme or a surface overrides the `--kit-*` variables, and the FloatingToolbar pill does. ActionBar keeps its own segments on the pill (ButtonGroup has no hook for the trigger's name, focus hold, pill shape or a filled segment).
- ButtonGroup's menu panel now uses `bg-popover` and `text-popover-foreground` in every theme (it was `bg-white`; same pixels in default light). The `--kit-paper` / `--kit-outline*` aliases stay: none of the shadcn surface tokens (popover, border, popover-foreground, muted-foreground, accent) equals the group's old gray/white values in the neutral sheet, so mapping them would change the default look. Hosts must load kit.css. Core re-pinned (see the todo).
- Menu family (built to the Dropdown, DropdownItem, DropdownLink, DropdownConfirm, DropdownAction, DropdownDelete, DropdownModalLink and ButtonDropdown specs, over the shared MenuPanel spec). `@kit/ui` exports `Dropdown`, `DropdownItem`, `DropdownLink`, `DropdownConfirm`, `DropdownAction`, `DropdownDelete`, `DropdownModalLink` and `ButtonDropdown`. The menu is not modal; it opens by pointer or keyboard (ArrowUp opens on the last item), never wraps, closes on Escape, Tab and outside presses with the focus rules the spec sets, and an item's confirmation dialog holds the menu open behind it. Enter and Space on an item do what a click does. As specced, `active` (DropdownItem, DropdownLink) and `noun` (DropdownConfirm) do nothing, DropdownLink's button form is always `type="submit"`, and an item with no `href` is an Inertia link to `#`. `Kit/Menu/DropdownMenu.vue` (an auto-wrapping icon menu) is shipped but not exported, because `DropdownMenu` is the shadcn primitive's name in `@kit/ui`. `ButtonGroup`'s menu now runs on the same MenuPanel (the default theme renders the same pixels; the panel is non-modal, so its trigger keeps its hover look while open). The confirmation dialogs use the kit's `Dialog`; the delete and danger icons use `text-destructive`. `composer test:js` runs the edge-placement test. Graduated from a client app's clean-room rebuild. Re-pinned: package `core` `sha256:b71a9bba…41e5` → `sha256:0d00abfe…c64b` (new stubs; `kit:update` delivers them).
- **Form inputs take the `Input*` names (0.3 rename).** `NumberInput` is now `InputNumber` and `Checkbox` is now `InputCheckbox`, in `@kit/ui` and as `Form/InputNumber.vue` and `Form/InputCheckbox.vue`. The old names stay for one minor as deprecated aliases and are removed in 0.4: the `@kit/ui` exports, the `NumberInputProps` type, and the `Form/NumberInput.vue` and `Form/Checkbox.vue` files, which are now the same component re-exported. `Select` is deprecated in favour of `InputSelect`, the kit's rich select, and stays unchanged until 0.4. It isn't an alias, because the props don't map one to one: InputSelect has no `autofocus`, and it turns search on past 8 options. The kit's own Login and Connections pages now use `InputCheckbox`. Run `kit:update` for `core`, `session-ui` and `mcp`, then rename your own imports before 0.4.
- **FormFieldset's title now sits inside its `<legend>`, as a heading.** FormFieldset is a native `<fieldset>`: the title is a real heading at `level` (default 2) inside the legend, and the description follows the legend, tied to the fieldset by `aria-describedby`. That is a UX change for screen readers: what they announce changes. The title is now the group's name as well as a heading, so moving focus into any field in the group announces the group's name, and the description is read as the group's description, not as part of its name. The prop is `title`; `legend` keeps working as a deprecated alias until 0.4. The title and description move from raw gray steps to the `foreground` and `muted-foreground` tokens, the grid gaps grow to 6 across and 8 between rows, and groups after the first are set apart by a rule (`separator="space"` for space only), decided by position among sibling fieldsets. Run `kit:update` for `core`.
- **LockedForm, EditOnly and `useLocked()`** (`Form/LockedForm`, `Form/EditOnly`, `Form/useLocked`, exported from `@kit/ui`; built to the LockedForm spec). `<LockedForm :save="save">` wraps one or more FormCards, or a lone FormFieldset, and locks them when the page's save descriptor is `null`: the server decides, never the client. Locked, every kit control turns read-only through the context, never `disabled`: native `readonly` on Input, PasswordInput, Textarea and InputNumber, `aria-readonly` on InputCheckbox, InputSelect (still a combobox, showing the chosen label and opening nothing) and the deprecated Select, with the `muted` fill and the value at full contrast. Edit actions are hidden, not disabled: Repeater's add, remove and move, FileInput's picker (it shows the current file's name, size and link from the new `current` prop, or "No file"), ActionBar descriptors with `intent: 'edit'`, and anything inside `<EditOnly>`. Each FormCard shows the notice in place of FormActions ("You can view these settings but not change them.", `quantity="one"` for the singular, `notice` to replace it, `reason` for a second muted line), nothing submits (Enter included), a nested LockedForm inside a locked one stays locked, and a FloatingToolbar inside the area shows the reason or "View only" as status text. `Tey\Kit\Features\Core\Data\Action` documents `intent: 'edit'`. `composer test:js` runs the new `tests/js/locked.test.mjs`.
- **FormFieldset rework** (built to the FormFieldset spec): a native fieldset and legend, `title` (`legend` stays as an alias until 0.4), `description`, `level` (2 to 6), `legendHidden`, `separator` (`rule` or `space`), `fullwidth`, the `title` and `description` slots, attributes on the fieldset, and a development warning for a fieldset with no title. Locking comes from LockedForm only: there is no `locked` or `disabled` prop and `<fieldset disabled>` is never used. A lone fieldset in its own LockedForm shows the notice under its legend and description, before its grid. Re-pinned: package `core` `sha256:20b806a1…71a6` → `sha256:4ac9554d…1d1a` (`kit:update` delivers the new and changed stubs).

- Entitlements per feature (#53). A feature or app declares that a capability is part of a plan: `$registry->entitlement('workspace.projects', limits: ['projects'], upgrade: ['label' => 'Upgrade to add projects', 'suffix' => 'settings/billing'])`. `Entitlements` resolves it per workspace: the workspace's row in `kit_entitlements`, then the app default (`kit.entitlements`), then not included. It is read once per request like capabilities. `Entitlements::enforce()` checks a limit on the server and refuses with `limit_reached` (403, with a plain message). A billing integration only calls `Entitlements::set()`; the kit has no billing provider. Entitlements are a separate gate from capabilities and permissions: `Access::authorize()` checks the entitlement after the capability. A capability left out of the plan is absent by default (`entitlement_unavailable`, 404, gone from the shell, settings and the MCP tool list). An entitlement that opts into `upgrade` refuses with `entitlement_required` (403) instead, and `Entitlements::upgrade()` gives the page a server-built upgrade action. There is no plan check in Vue or TypeScript. `composer test:entitlements` covers two features with different plans on one workspace, a limit over HTTP, and the separation from capabilities and permissions. Runs in CI. Re-pinned: package `access` `sha256:25d5b8be…38548` → `sha256:a179066f…26d98` (a new migration stub; `kit:update` delivers it).

- `kit:adopt <feature> plan|apply [--from=<release>]` (#3) records a feature that is already on disk as its own lineage. It overwrites nothing. Each file is handled by what it holds:
  - **Identical** to a known release: claimed with no write.
  - **Missing:** created.
  - **Edited:** kept by the app, against the release it was edited from. That is the `--from` release, or by default the closest one (the release most files match). A later `kit:update` leaves a kept file alone until a release changes it. It is then a conflict, as an edited owned file is. Once the app's bytes match the release, the file is claimed.

  A failed apply rolls back, as `kit:update` does. If an apply is interrupted (a crash), every plan refuses with the pending hint, which names `kit:adopt <feature> rollback --intent=<intent> --exclusive-checkout`. The rollback removes the files the adoption created, and refuses, removing nothing, if one has changed since. `resources/features/history.json` holds the file hashes of each tagged release; regenerate it with `php bin/catalog-history.php` before tagging a release. Its `current` entry must match the live catalog, and the check fails otherwise, so a stub change regenerates it in the same PR. `composer test:adopt-in-place` covers it: a clean-room pre-lifecycle host, adopt, update and a follow-up update, with the edit kept throughout.

- **Environment indicator (#50).** Outside production the shell shows where you are without a badge: `[Staging] ` before the document title and a 2px edge across the top of the signed-in shell, in the environment's colour (with its name for a screen reader). It is configured per environment in `kit.environment_indicator` (`label`, `colour`), ships with a `staging` entry only (`#b45309`, 5.0:1 on white and 3.9:1 on the dark shell), carries no product name, and is never shown in production whatever the config says. A label that is not plain text, or a colour under 3:1 on the light or dark shell (the brand's own page colours when set), is not shown. `PageTitle` applies the prefix; the server shares `environment` (null where there is none) for a host's own layouts, such as a guest page, to draw the same edge.
- **Brand theming (#49).** `kit.brand` now expresses a whole theme, and every key is optional: unset, the CSS is byte-for-byte what v0.2.2 printed (a check asserts it).
  - **Surfaces:** `background`, `card`, `border`, `muted` and their `_dark` twins print `--background`, `--card`, `--border` and `--muted`.
  - **Roles:** `button` (`accent` or `ink`), `button_dark` (`accent` or `theme`), `header` (`theme` or `ink`) and `marker` (`strong` or `accent`) split what `style` bundled. `style=mono` is the same as `button=ink, button_dark=theme, header=ink, marker=accent`, so ink buttons in light mode and accent buttons in dark mode on the theme's own header is `button=ink, button_dark=accent, header=theme`.
  - **Links:** `link` and `link_dark` set `--brand-link`; nothing is printed unless set.
  - **Header rule:** `header_rule` (`accent` or a `#rrggbb`) draws a 2px line under the header (`accent_dark` in dark mode).
  - **Logomark and lockup:** `logomark_dark` is shown under `.dark`; `lockup` shows the `wordmark` image (`wordmark_dark` in dark mode), or the product's name when there is none, beside the mark from the `sm` breakpoint up. The shell's `product` gains `logomark_dark`, `wordmark`, `wordmark_dark` and `lockup`; a host's own guest layout reads them from `shell.product`.
  - **Fonts:** `KIT_FONTS=inter-serif` pairs Inter (text) with Instrument Serif (display).
  - **Contrast:** `contrastProblems()` reads the accent and the links against the configured page, card and dark surfaces instead of `#ffffff` and `#09090b`, and checks `link` and `link_dark`.

### Changed

- Renamed `NumberInput` to `InputNumber` and `Checkbox` to `InputCheckbox`, with deprecated aliases until 0.4, and deprecated `Select` in favour of `InputSelect` (see Upgrading). Re-pinned: package `core` `sha256:0d00abfe…c64b` → `sha256:2507f221…b510`, `session-ui` `sha256:0e9134ce…3bdc` → `sha256:241edd9b…c934`, `mcp` `sha256:e422e9e4…daa4` → `sha256:f507a2bb…117b` (`kit:update` delivers them).

- SplitPane, PaneScroll, PaneTabs, PaneToggle and BulkDrop in the core Kit stubs (`resources/js/components/Kit/Layout/`, `Kit/Form/BulkDrop.vue`). SplitPane is work beside its context on Reka's Splitter, with the aside in a drawer below the breakpoint; its layout state is server-first through an optional preference descriptor, with a guarded localStorage fallback. The preference write goes through Inertia's HTTP client (`http` from `@inertiajs/vue3`), so a host's `http.onRequest`/`onResponse`/`onError` interceptors apply and the client sends the XSRF token; a failed write falls back to localStorage. BulkDrop is the one dropzone (drop, paste on the focused surface, or browse) and emits files only: no upload transport, FileViewer or preference endpoint ships. `composer test:split-drop` covers the storage, the sender and the selection rules. Graduated from a client app. Re-pinned: package `core` `sha256:2507f221…b510` → `sha256:20b806a1…71a6` (new stubs; `kit:update` delivers them).

### Fixed

- `FluentMailMessage` no longer fails with "View [preheader] not found" when `Illuminate\Mail\Markdown` was resolved before the first message (a queue worker, an earlier mail). The kit's mail paths now also reach an already-resolved renderer. `composer test:mail` resolves the renderer first, then renders a message with a preheader.
- The kit's branded mail views are appended to `mail.markdown.paths` instead of prepended, so a host's published `resources/views/vendor/mail` (header, footer, themes) wins over them; the kit's views still apply where the host has none. `composer test:mail` covers both.
- `mail.brand` carries the `accent_foreground`, `accent_strong` and `ink` tokens; the branded button's text uses `accent_foreground`. `docs/mail.md` lists the keys and how a host adds its own.
- The kit's invitation email (`InviteNotification`) is composed with `FluentMailMessage`; the `kit-mail::invitation` view and the `kit-mail` view namespace are gone. `composer test:mail` runs `MailConformance` on the kit's own `src/`.
- Generated TypeScript types no longer read `undefined` for actions in a host that has laravel-actions. Its global `Action` alias won over the docblocks' imported `Action`, so the transformer typed the shell's workspace `settings`, `all` and `leave`, list items' `actions` and the workspace index's `leave` as `undefined`. The kit's docblocks now name `Tey\Kit\Features\Core\Data\Action` fully qualified, and `composer test:library` refuses a bare one. Regenerate the app's types (`composer types:generate` in the starter).
- Settings › People stayed reachable by URL with `workspace.settings` off (found by the capability guard). `WorkspacePolicy::manageMembers` now denies as not found while the capability is off.
- **ComputedValue's phone sheet spans the viewport.** On a 390px phone the sheet stopped at 375px: the modal package's scroll lock reserves a scrollbar gutter on `<html>`, which narrowed the sheet's fixed container by the scrollbar's width. `kit.css` now releases the gutter below 640px while a modal is open, so every phone-width sheet and modal is edge to edge (verified at 360, 390 and 430px).

## v0.2.2

### Added

- **InputSelect**, the kit's rich select (`Form/InputSelect`, exported from `@kit/ui`), built to the InputSelect spec on Reka's Combobox. It picks one value or many (as chips, or a count past `maxChips`) and stays non-modal while open. Search turns on past 8 options. It handles groups, remote search (debounced, from 2 characters), clear, opt-in select all, chosen first and create, read-only, and the phone sizes. With `name` it submits native `name` / `name[]` inputs. Its strings ship in the kit's new `lang/en/ui.php` (`kit::ui`, shared with pages as `kitUi`). Override them for the app in `lang/vendor/kit/en/ui.php`, or for one use with `messages`. The existing `Select` is unchanged.
- **Known limitation (InputSelect):** Reka's combobox has no supported way to stay non-modal, so InputSelect relies on an undocumented Reka behaviour (no page hiding inside a closed popover), verified on reka-ui 2.11.0. A Reka update could make the open list hide the rest of the page from screen readers again; re-check on each Reka bump.

## v0.2.1

### Fixed

- **MCP connections survive OAuth token refresh.** A connection was bound to one access token, so every MCP client lost its connection (and its workspace) at the first refresh, about an hour in. The `mcp` feature now moves a live connection to the new access token when Passport issues it. It moves only when the connection's old token was spent (revoked by the refresh, or expired) and belonged to the same user and client. A revoked connection stays revoked, and the old token is refused.

## v0.2.0

v0.2.0 is the kit proven in a mature production app. It adds the signed-in shell, people, settings and account pages, workspace permissions and per-workspace capabilities, and graduates Repeater, NumberInput, ScrollList and ComputedValue into the core Kit. It also makes `kit:update` safe on an app that adopted features one by one, and closes a workspace-name disclosure.

### Upgrading

- **Repeater row errors:** the kit's `rowErrorsKey` is its own `Symbol`. If your app has a local `repeaterState` (or a local copy of the key), import the kit's instead: `import { rowErrorsKey } from '@/components/Kit/Form/repeaterState'`. Otherwise fields inside kit Repeater rows (NumberInput included) stop receiving their row errors. The key is not exported from `@kit/ui`.
- **ScrollList:** the default row is slimmer (title, description, meta, badges). Pass the `row` slot (`{ item }`) for a richer row. The row hook `data-queue-row` is now `data-list-row` (rows also carry `data-list-item`). `useScrollList` no longer returns a total, because the list is count-free (cursor only).
- **ComputedValue:** the `data-candidate-*` hooks are now `data-kit-*` (`data-kit-popover`, `data-kit-sheet`). Update selectors and tests.
- **Repeater:** drag-to-reorder is removed. Rows move with the move buttons, which are always visible.
- **kit:update on an app that adopted features:** update each feature with `kit:update plan|apply --lineage=<feature>`, in dependency order, giving each lineage its own `--intent`. Plain `kit:update` and `--target` (release fixtures) are refused there and print the commands. A failed `kit:update apply` now rolls back: no files or journal stay behind. Fix the cause, then plan and apply again.
- **Run `kit:add` and `kit:update` from the app's main checkout,** not from a git worktree.
- **Apps installed before feature lineages** cannot `kit:update` yet (#3).
- **Workspace reads are member-only:** `MemberWorkspaces::workspace()` returns null for a workspace the person is not in. Use `WorkspaceUrl::currentMemberId()` for anything rendered, and pass a `Workspace` model (not an id) when building a link outside a session, such as mail or a queued job.
- **Brand mail is opt-in:** mail carries the brand only with the session-ui feature or when `kit.brand_mail` is `true`.
- **Migrate before `/up` is healthy:** the health checks need the tables of the features you adopted (new migrations: `kit_capability_overrides`, `kit_permission_defaults`, `constrain_audit_events_workspace_id`). Run `php artisan migrate` and `kit:permissions:sync` on deploy.
- **Dialog and Modal** replace `ConfirmDialog` and `FormModal`; **Modal** now returns focus to its opener, and Dialog gains `@closed`.

### Added

- Repeater and NumberInput graduate into the core Kit (`Form/Repeater`, `Form/NumberInput`): repeating rows with add, remove and move buttons and per-row errors, and a number field that keeps its text while typed. `kit:update` delivers them with `core`. Graduated from a client app.
- ScrollList graduates into the core Kit: a cursor-only list with an `Load more` button, client-side or server-driven (`useScrollList`), a slim default row and a `row` slot (`{ item }`) for a richer one. Graduated from a client app.
- ComputedValue: a figure that opens its working (the ComputedValue spec and conformance rules 17 and 70). `@kit/ui` exports `ComputedValue`, `ComputedValueRoot` (headless), `ComputedValueScope` (show or hide all, optionally remembered) and `ComputedValueToggle`, plus the `Explanation*` types. `core` generates them into `resources/js/components/Kit/Explanation/`. The server sends a `Tey\Kit\Explanation\ExplanationDocument` (Laravel Data). It refuses JSON floats, unsupported versions, dangling operands, unsafe links, malformed decimals, cycles and documents deeper than 16, larger than 1000 nodes or 256 KiB. The browser only displays the strings it is given and does no arithmetic. `php bin/generate-explanation-ts.php` writes `explanation.generated.ts` from the PHP types, and `--check` fails when it is stale. The desktop popover and phone sheet that ComputedValue opens are internal and not exported. `composer test:explanation` covers the contracts, the generator and the working text. Graduated from a client app. Re-pinned: package `core` `sha256:748e41c2…fe0d6` → `sha256:f3c7a801…81a2d` (new stubs; `kit:update` delivers them).
- Permissions. A feature (or an app) declares a permission and its default Concierge roles on the registry: `$registry->permission('workspace.settings.update', ['owner', 'admin'], capability: 'workspace.settings', abilities: ['settings.write'])`. The kit declares `workspace.settings.update` and `workspace.members.manage`. `php artisan kit:permissions:sync` creates the missing permissions and grants the defaults, re-runs change nothing, never revokes, and reports custom grants, undeclared permissions and default roles the app does not configure (skipped), and default roles it configures but has not created (exits nonzero). Roles stay Concierge's, per workspace; the kit adds no role system. `WorkspacePolicy` (`view`, `update`, `manageMembers`) checks active membership, the role grant in the workspace's team context and the permission's Pennant capability, all three. `Permissions::flags()` gives a page its `can` map from those checks. `Access::authorize()` decides an ability a permission replaces (`settings.write`) by the permission, not by ownership; other `.write` abilities stay owner-only. The settings page's `canWrite` comes from the policy. Run the sync after `migrate` on deploy. A default is granted once: the `access` feature's new `kit_permission_defaults` migration records which defaults the sync has applied, so a default someone removed is not granted again, and one that is new since the last sync is. `HandleInertiaRequests` shares the current workspace's `can` map. `composer test:permissions` (registry, sync, policy, flags) and `composer test:permissions-http` (settings update over HTTP on real Concierge tables and spatie teams: admin allowed after the sync, member 403, capability off 403) run on SQLite memory. Run in CI. Re-pinned: package `access` `sha256:5030011a…e38b0` → `sha256:26bbe931…86528` (a new migration stub; `kit:update` delivers it).
- Capabilities per workspace and person (#51, #52). A capability can be turned on or off for one workspace, or for one person in it, over the global switch. `php artisan kit:capability <workspace> [capability] [on|off|clear] [--user=] [--operator=] [--reason=]` lists or changes the overrides; the `access` feature's new `kit_capability_overrides` migration stores them. A capability that is off is absent everywhere, never shown as forbidden, and a registry guard checks the permissions that name one. Membership, permissions and overrides are read once per request.
- The shell: a dark bar with ghost controls, a split workspace switcher (one list, with leave in its menu), a back strip and breadcrumbs as a sub-bar (gretel, one crumb per page route in both URL forms), page titles as `{Page} · {Product}`, `FocusShell` for signed-in pages outside a workspace, `GuestShell` for guest pages and `FocusPage` for the page measure. The mark is the platform identity (`APP_NAME` and a configurable logomark), never a workspace name.
- Workspace URLs: tenants are marked with `@` (`/@{workspace}/...`, short paths, `/me/workspaces`), with one URL helper. The dashboard is the workspace root (`/@{workspace}`, short form `/`); `/dashboard` redirects (301).
- Pages as typed ViewModels: one shell share, `layout()` with navigation, titles and breadcrumbs, persistent section layouts, action descriptors in place of `can` maps and flags, and the `Page` container (boolean widths, states).
- Settings: a landing page of cards with a section sidebar, sections registered by providers, a `Placeholder` component and `Registry::placeholder` for a planned section, and a save that is always shown. Connections and the AI assistants page (approval descriptors and assistant cards) are Inertia pages.
- People: members and invitations in Settings with invite, change role, remove, resend and revoke through Concierge; transfer ownership (owner only, password confirmed again, the old owner becomes an admin); leave workspace; a transfer and leave in the General danger zone.
- Invitations: a branded email with the inviter's personal message, a specific subject and a preheader; an accept page that shows the workspace and inviter, signs in or creates the invited account, joins as the current account and says who invited you.
- Account pages under `/me`: profile with email re-verification, password, security with two-factor, devices and a security log. Deleting an account deletes what the kit remembers about the person.
- Avatars: a Media Library collection on the user, upload, replace and remove on `/me/profile`, served from the private media disk by a cached `/avatars/{token}` route; initials are the fallback.
- Brand: brand config, `kit:brand` assets, head links, branded mail with a footer from the registry, a private brand mode, accent companions, fill and mono styles, config-driven presets, a local-only `?brand=` switch and a 4.5:1 contrast guard. Mail address roles: replies go to support, with a privacy contact in the footer.
- First run: welcome steps, a dashboard welcome card, invitation forks and a one-click demo; the timezone is captured with a `tz` cookie, an auto-saving wizard step and the owner's workspace timezone; the no-workspace page names the product in the bar.
- Deploy: `kit:preflight` (with `--env-file` to rehearse a deploy, and Passport keys in any form a dashboard can hold), `/up` health checks, optimize registrations, and roles and permissions synced when migrations end.
- `Dialog` and `Modal` replace `ConfirmDialog` and `FormModal`; Invite and Change role are server modals. `Dialog` gains `@closed`, which fires once it has closed and returned focus. Also new: `Alert`, `Toolbar`, `BlockStack` settings cards, read-only input styling and an `autofocus` prop, a kit stylesheet with the pointer cursor, and release blueprint E for tey/concierge 0.8.

### Changed

- The workspaces feature requires tey/concierge `^0.8.1`; the default Concierge local sign-in is limited to the local environment.
- Forms are `FormActions` and `FormCard` on Inertia `Form` pages with a sticky status bar, a leave warning whenever a form holds unsaved input and confirmations on destructive actions; tables are `DataTable` over Concierge's `MembersIndex`; listings use the `QueryBuilder` and resource controllers, and invitations live under `/me`. Controller input-limit errors map to 400.
- Workspaces are ordered by their key, and a layout can name its own icon links.
- `kit:update` on an app that adopted features works per feature. Update each with `kit:update plan|apply --lineage=<feature>`, in dependency order; plain `kit:update` and `--target` are refused there and print the commands.
- `Modal` returns focus to its opener when it closes, and its Close button is 44px on phones. The phone sheet behind ComputedValue is labelled by its title, takes focus once it has opened and returns it on close.

### Fixed

- `kit:update apply` fails closed. A fresh apply that does not complete restores every planned file (created files and their empty directories are removed, changed files get their prior bytes back) and drops the intent's journal, so no files stay behind. A reopened intent still resumes.
- ScrollList keeps focus while it loads more: `Load more` uses `aria-disabled` and ignores clicks while busy instead of becoming `disabled`, so keyboard focus is not dropped to the page.
- Modal and Discard & close: the confirmation closes first and the modal closes after it, so focus returns to the opener rather than the page body (a plain Escape close did the same before).
- DataTable phone cards show their field labels (#55). The label is chosen by visible column position instead of being the first child of every cell; the title column and the action labels stay hidden.
- A security log that cannot be written never blocks a sign-in; a scrubbed security-log failure is reported. Input-limit errors register only on the framework handler, so artisan boots under Collision.
- Fixes to the first release of these pages: modal layering, the People member sort, bad table queries and modal redirects; the home crumb is the workspace root; breadcrumb links, the personal chooser and settings sections.
- A host that adopted only some features boots only those. `FeaturesServiceProvider` registered the `/up` health checks, the mail brand and the post-migrate `CatalogSync` whatever was adopted, so a core-only host answered `/up` with 500 ("the table workspaces is missing"), had `mail.from` and `mail.reply_to` rewritten and ran a sync it had no catalog for. Now the health checks require `workspaces` and `concierge_memberships` only with the workspaces feature, `roles` only with access and `kit_meta` only with core, and skip the brand placeholders on an unbranded host; the brand reaches mail (and is judged by `/up`) only with session-ui, or when `kit.brand_mail` is `true` (`false` turns it off for session-ui); `CatalogSync` is registered only with access. Nothing adopted registers no health listener. `php tests/HostBootCheck.php` boots core-only, nothing-adopted and full hosts on SQLite memory. Re-pinned: package `core` `sha256:77383e5f…ac422` → `sha256:07be2974…4983c`.
- Core migrates alone. `audit_events.workspace_id` was `foreignId(...)->constrained()` in the core migration, so a core-only host failed on Postgres (no `workspaces` table; SQLite let it through). Core now creates it as an indexed `unsignedBigInteger`, and the `workspaces` feature's new migration `0001_01_02_000204_constrain_audit_events_workspace_id` adds the foreign key to `workspaces` when it is missing. A database that already has the key (every host that ran the old core migration with workspaces) is left as it is; `down` changes nothing. `Audit::write` is unchanged. Checked on Postgres: core alone migrates with no key, adding workspaces adds it (an unknown workspace is refused), a re-run is a no-op, and an existing full host's keys are unchanged.
- `Overlay/Modal` types its package instance by the one method it calls (`close`), so `vue-tsc` no longer fails where `@inertiaui/modal-vue` 3.1.2 ships no declaration for its Modal component; unused refs and optional-prop defaults that tripped stricter compiler options are gone.
- `Form/Button` no longer needs a `bar` variant in the host's `components/ui/button`: `bar` renders the standard `ghost` variant with the shell bar's foreground colour classes. Props and `ButtonProps` are unchanged.
- Core's `requires.npm` carries real ranges instead of `*`: `@inertiaui/modal-vue` `^3.1.2`, `@lucide/vue` `^1.0.0`, `@tanstack/vue-table` `^8.0.0` (9 breaks `DataTable`). Still informational: `kit:add` does not install them. Re-pinned: package `core` `sha256:e84aceae…` → `sha256:8354c2cf…`, `workspaces` `sha256:ed0a9139…` → `sha256:8aa53c04…` (a new migration stub; `kit:update` delivers it).
- Core declares what its components import. `requires.npm` lists `@lucide/vue` and `@tanstack/vue-table` (informational; `kit:add` does not install them) and `requires.host_ui` lists the host-owned `spinner` component (`@/components/ui/spinner`). `DataTable` stays exported from `@kit/ui`, so existing imports keep working.

### Security

- A workspace the person is not a member of is no longer read for display. `MemberWorkspaces::workspace()` used to fall back to any workspace by id, so a page could show the name or slug of a workspace the signed-in person does not belong to. It now returns only the person's own active workspaces (null otherwise, and for no person). `WorkspaceUrl::currentId()` is documented as not authorization; the new `WorkspaceUrl::currentMemberId()` is the same id only for an active member, and the title, crumbs, shell, settings nav and home redirect use it. `WorkspaceUrl::slug()` (and so `full`, `to` and `shareable` with an int) refuses an id the signed-in person is not in with `workspace_not_found` instead of building a link with an empty slug; in the console with no one signed in (mail, queue, commands) the id resolves unscoped and is refused only when unknown, while a guest HTTP request, or any matched route in a long-lived worker, is refused. Pass a `Workspace` for a link outside a session. Operations still authorize, and a non-member and an unknown slug are refused alike (404). `composer test:workspace-guard` runs on SQLite memory. Run in CI.

## v0.1.5

### Added

- Font sets (#43). `kit.fonts` (`KIT_FONTS`, default `instrument`) names one of three sets: `instrument`, `instrument-inter` or `hanken`. `<x-kit::fonts />` in the layout head emits that set's fonts.bunny.net links and a `:root` block with `--font-sans`, `--font-serif` (display), `--font-mono` and `--font-display-weight`, with system fallbacks. An unknown set name is refused. `composer test:fonts` renders each set, checks the refusal and checks that pages name no font family but those variables. Run in CI.

## v0.1.4

### Fixed

- The `user_model` host step also checks the trait (#40). A user model that implements `Tey\Concierge\Contracts\Member` without using `HasWorkspaces` (through `class_uses_recursive`) is unmet, and the step says which half is missing. `composer test:install` covers a User with the contract only: the preview lists the step as unmet and the apply refuses.
- Host step validation accepts the `implements` and `uses` keys in any order (#41), and still refuses a missing, extra or non-string key. `composer test:catalog` covers both orders and each refusal.

## v0.1.3

### Fixed

- `kit:add workspaces` names the user model host step (#36). The `workspaces` manifest declares it in a new optional `host_steps` field: the user model must implement `Tey\Concierge\Contracts\Member`, through the kit's `HasWorkspaces` trait. `kit:add` checks it by reflection. It checks that `kit.models.user` (else the `users` auth provider's model) and the `concierge.models.user` the app will run with name the same model, and that the model implements the contract. Until then the preview lists the step as unmet (`host step (unmet)`, and `unmet` in `--json`) with the exact lines to add, and `--apply` and `--resume` refuse. Kit never writes `app/Models/User.php`. Re-pinned: package `workspaces` `sha256:7d57c61a…87926` → `sha256:e3b16619…3976a`.
- Adopted features register in an app set up by `kit:install`. Package providers register before the app's own, so `KitHostServiceProvider` was not yet bound when the kit read the adopted features, and nothing registered. Concierge then failed `php artisan migrate`. `FeaturesServiceProvider` now falls back to `kit-lifecycle.app_id`.
- `composer test:install` covers both: on a fresh app the step is listed and the apply refused. With the lines added, the apply completes and `php artisan migrate` succeeds on SQLite memory. The fresh-app fixture gains Laravel's users and cache migrations. The AdoptCheck host app's `User` now implements `Member`.

## v0.1.2

### Added

- `php artisan kit:install` sets up the host binding, so `kit:add` works in a fresh app (#34). It writes app-owned files from stubs in `resources/install`: `app/Kit/KitHost.php` (a `LifecycleHost` and `CatalogHost` on the package catalog), a fail-closed operation check in `app/Kit/operations.php`, `config/kit-lifecycle.php` (verification policy; app id from `app.name`), `config/kit-executor.json` from `Lifecycle::runtime()`, and `app/Providers/KitHostServiceProvider.php`. It appends the provider to `bootstrap/providers.php` only when missing, prints what it wrote and what to review, refuses to overwrite any existing file, and a second run changes nothing.
- `composer test:install` on a fresh-Laravel-shaped fixture with no host binding: `kit:install` then `kit:add workspaces` previews, applies on SQLite memory, a second install is a no-op, an existing `app/Kit/KitHost.php` is refused, and `kit:add` still refuses without it. Run in CI.
- README: a quick start at the top.

## v0.1.1

### Changed

- `kit:add` claims existing files only from an explicit, reviewed list (#26). v0.1.0 claimed every identical pre-existing file implicitly. Now the preview lists them as `claimable`, and the apply is refused until each one is listed with `--claim=<path>` (repeatable or comma-separated). Only listed paths reach Mod's `claim:`. The claim list is part of the plan identity, so a reviewed plan can't change silently. A listed file with different bytes stays a conflict, and a claim no feature being added delivers is refused.
- `kit:add --resume --intent=<id>` finishes the interrupted feature, then every remaining feature of the original request in requires order (#28). It prints which features are done and which remain, and exits nonzero unless every one completed. A resume whose intent has no pending record for the next feature is refused plainly.

### Fixed

- A `pending:<lineage>` refusal from `kit:add` or `kit:update` now prints the pending lineage, its intent and the exact `--resume --intent=<id>` command to run, or where to find the intent, and exits nonzero (#27).
- Tests cover a differing unowned pre-existing file (refused, no write), `--resume` without `--intent` (nonzero, clear refusal), explicit claims, the pending hint and multi-feature resume (#29).

## v0.1.0

### Add starter features to an existing app

The first tey/kit release. `composer require tey/kit`, then `php artisan kit:add <features>` previews and applies the starter's features (core, workspaces, access, settings, session-ui, mcp) one Mod 0.9 lineage per feature, claiming every identical existing file without asking (explicit from v0.1.1) and refusing to overwrite edits. `kit:update` upgrades each feature after `composer update tey/kit`; `kit:status` and `kit:list` report.

### Changed

- Requires released `tey/mod` ^0.9 and `tey/concierge` ^0.7.3. Release fixtures A–D and the v0.1 feature fixture keep the single-lineage `Lifecycle` path, which now reads Mod 0.9's per-lineage installed records.
- Re-pinned for Mod 0.9: the A–D manifests and the v0.1 `foundation` feature now require `tey/mod` ^0.9 instead of ^0.8. The package `workspaces` feature requires `tey/concierge` ^0.7.3 instead of exactly 0.7.2. Old to new: A `sha256:686b417b…5c74b` → `sha256:a59a38d4…c8260`, B `sha256:857fc187…2b3b` → `sha256:53e31b60…74d59`, C `sha256:18e53f09…7bdb` → `sha256:8699fd47…caf73`, D `sha256:777333b1…dd0f` → `sha256:0bab54b8…83672`; v0.1 `foundation` `sha256:40bbc9be…3541` → `sha256:a8f47c15…21312`, v0.1 all features `sha256:db4358fe…956675` → `sha256:40df4082…c9b98`; package `workspaces` `sha256:068735ea…45d5ba` → `sha256:7d57c61a…87926`.
- Mod 0.9 rejects dot-leading `hostOwned` prefixes (`.env`, `.env.example`). Until Mod 0.9.1, those are left out of the Mod artifact and enforced kit-side only.
- Renamed from `tey/starter-blueprint` to `tey/kit`. This package has never been published and nothing depends on it, so there are no aliases. Namespace `Tey\StarterBlueprint` is now `Tey\Kit`, `BlueprintServiceProvider` is `KitServiceProvider`, the config key `starter-blueprint` is `kit`, `starter:maintain` is `kit:update` (same arguments and options), and the scaffold `tey/starter-foundation` (command `starter:foundation`) is `tey/kit` (command `kit:foundation`). Other class names are unchanged.
- Pinned identities changed with the scaffold id: A `sha256:21bd1704…45e2` → `sha256:686b417b…5c74b`, B `sha256:70af6427…726c` → `sha256:857fc187…2b3b`, C `sha256:85405850…a3ff` → `sha256:18e53f09…7bdb`, D `sha256:a85ea6fe…7aaf` → `sha256:777333b1…dd0f`; feature `foundation` `sha256:8e2e55ee…4054` → `sha256:40bbc9be…3541`, `defaults` `sha256:fb252b8b…2e43` → `sha256:71662b1b…ec7f6`; all features `sha256:a7324f25…475b9e` → `sha256:db4358fe…956675`.
- CI runs every `composer test:*` script on PHP 8.4 and 8.5, and a Privacy job.

### Added

- Per-feature lineages on Mod 0.9 (`Lineages`): each feature installs as its own lineage, `tey/kit:<feature>`. Feature requires are checked kit-side against the installed versions and passed to Mod as `requires:<id>` outcomes; `host_owned` becomes Mod's `hostOwned`; `conflicts` stay kit-side.
- `kit:add <features...>`: previews by default (plan identity, per-file create/claim/conflict, missing packages, host steps) and writes nothing. `--apply --expected-plan=<id> [--intent=<id>]` adds the features in requires order, one lineage each, and stops at the first failure. `--resume --intent=<id>` finishes it. Every identical pre-existing file is claimed implicitly, without a write; v0.1.1 claims only listed files. Re-adopting is a no-op, and nothing is ever removed.
- `kit:update --lineage=<feature>`: plan, apply or resume one adopted feature's lineage. Refused while any lineage has an unfinished update. The original is rebuilt from the installed record, so an app upgrades to a new catalog version after `composer update tey/kit` without the old catalog.
- `FeaturesServiceProvider` adopts the installed lineages (read for `kit.app_id`, else the bound host's app id); `kit.features` stays an explicit override.
- `composer test:adopt` on a minimal non-starter host app fixture, run in CI.
- `kit:status` reports every installed lineage (`lineages`: name, version, identity, features, file states) instead of one `artifact`; `blueprint_features` is the union.
- `kit:list`: prints the catalog's features with what each requires and owns. Read-only.
- `Blueprint::entries()`: the same listing as data.
- `bin/privacy-check.sh`, `AGENTS.md` and `CLAUDE.md`.
- `Catalog`: a feature catalog outside the package, with its own pinned identities. `Catalog::at($root, $pins, $basePin)` builds one, `Catalog::package()` is this package's own. `Blueprint::features()`, `Blueprint::all()` and `Blueprint::featureIds()` take an optional catalog and default to the package catalog. An external catalog's identities are checked as strictly as the package's.
- `files_from` in a feature manifest: a tree of stubs expands into generated files, sorted, with symlinks, escapes and duplicate targets refused. The feature identity covers every expanded stub.
- `CatalogHost`: a `LifecycleHost` that also implements it supplies the catalog `kit:update` builds its blueprints from.
- A seven-feature fixture catalog and `tests/CatalogCheck.php`, run in CI.

## starter-blueprint v0.1.0 (predecessor package)

### Added

- Per-feature manifests, `foundation` and `defaults`, each declaring its owned files, requirements, conflicts and host-owned paths. Every feature is content-addressed into the blueprint's source reference, and the whole app is all features.
- First adoption with no existing baseline, and add-only upgrades as the feature selection grows. Removing a feature is refused with a clear message.
- `starter:maintain {plan|apply|resume}` with `--original`, `--target`, `--features`, `--original-features`, `--intent`, `--expected-plan` and `--exclusive-checkout`. Every refusal or incomplete run exits nonzero.
- The `tey/starter-foundation` scaffold for previewing and generating the files through Mod's public scaffolds.
- The `LifecycleHost` contract an app implements for its app ID, bindings, input files and operation check, plus executor provenance and input fingerprints bound into each run.
- An upgrade's original must equal the installed artifact exactly, metadata included, or it is refused before Mod runs.
- Pinned release fixtures A–D: A `sha256:21bd1704…45e2`, B `sha256:70af6427…726c`, C `sha256:85405850…a3ff`, D `sha256:a85ea6fe…7aaf`; feature `foundation` `sha256:8e2e55ee…4054`, `defaults` `sha256:fb252b8b…2e43`.
- Requires `tey/mod` ^0.8 and `tey/concierge` ^0.7.2. CI proves the lifecycle on PHP 8.4 and 8.5 against the released packages: first adoption, plan and apply, additive upgrade, refused removal, an unowned path, resume and a cold retry. Adopting into an app that already has files the blueprint would own is refused until Mod can claim existing files.
