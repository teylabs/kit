# The starter catalog

tey/kit ships the starter's features as its package catalog, `Catalog::package()`: `core`, `workspaces`, `access`, `settings`, `shell`, `session-ui`, `workspace-ui`, `mcp` and `notes`, in that canonical order. `notes` is opt-in (a host adopts it with `kit:add notes`), and `session-ui` alone (without `workspace-ui`) is the sign-in only, needing just `core` and `shell`. Each feature has a manifest at `resources/features/<feature>/feature.json` and its stubs beside it. Its identity is pinned in `Catalog::FEATURES` (and checked again in `tests/StarterCatalogCheck.php`).

The v0.1 `foundation` and `defaults` features and the A–D releases are regression fixtures only (`tests/fixtures/v01`, `resources/releases`). They are not in this catalog.

## Library or generated

Under Mod 0.9, any edit to a generated file is a conflict on that feature's next update, even when the update leaves that file unchanged. So the rule is: **library by default; generate only what an app is not expected to edit, or what must live in the app to work at all.**

| Kind | Where | Why |
|---|---|---|
| Classes: models, operations, controllers, middleware, MCP tools, data objects, providers, the registry | Library: `src/Features/<Feature>`, `Tey\Kit\Features\...` | Apps extend them (models, controllers and operations aren't `final`) or configure them. Updates arrive through Composer. |
| Routes | Library: `src/Features/<Feature>/routes/*.php` | Routes are what apps tweak most. A generated route file would block every later update of its feature. Configure them through `kit.routes.<feature>`. |
| Config defaults | Library: `config/kit.php` and `config/defaults/<package>.php` | Apps customise config all the time, so no feature generates any. `FeaturesServiceProvider` merges `kit.php`; each feature's provider lays its package defaults over the package's own (`Core\Defaults`). |
| Migrations | Generated: `database/migrations/kit/<feature>` | Migrations have to exist in the app to be run and recorded. Apps don't edit them. Each is prefixed `0001_01_02_000NXX_`, where N is the feature's place in the requires order (core 1, workspaces 2, access 3, settings 4, session-ui 5, mcp 6, notes 7), so they run after the host's `0001_01_01_*` migrations and a later feature can never reorder foreign keys. |
| Vue pages and components | Generated: `resources/js/pages/<Resource>/<Action>/Page.vue`, with PascalCase folders and `Partials/` for page-local pieces | Unstyled starting points that the app's Vite build compiles: native elements, no authored class names, no `<style>`. Copy them rather than editing them. |

### Per feature

| Feature | Requires | Library classes | Generated files |
|---|---|---|---|
| core | – | 7: Audit, Principal, Registry, Users, Contributes, Defaults, CoreProvider | migration `000100_create_kit_core_tables` (audit_events) |
| workspaces | core | 9: Workspace, TenantBoundary, WorkspaceCache, HasWorkspaces, ProvisionWorkspace, AcceptWorkspaceInvitation, WorkspaceController, IndexWorkspaceViewModel, WorkspacesProvider | migrations `000200_create_kit_workspaces_table`, `000201_create_permission_tables`, `000202_create_kit_operators_table` |
| access | workspaces | 4: Access, Entitlements, ComplimentaryAccess, AccessProvider | migrations `000300_create_kit_access_tables`, `000301_create_kit_permission_defaults_table`, `000302_create_kit_capability_overrides_table`, `000303_create_kit_entitlements_table` |
| settings | workspaces, access | 10: WorkspaceSettings, WorkspaceProfile, SettingsView, SettingsUi, SettingsController, EditSettingsViewModel, SettingsProvider, Integrations/Mcp ×3 | migrations `000400_create_settings_table`, `000401_create_kit_settings_tables`; `pages/Workspace/Settings/Edit/Page.vue` |
| shell | core | 3: ShellProvider (contributes nothing), ShellData, ShellKind | `pages/Workspace/Layout.vue`, `pages/Workspace/Partials/` (GuestShell, SubNav and Wordmark among them) and `types/kit-shell.d.ts` |
| session-ui | core, shell | 5: PageShell, HandleInertiaRequests, LoginViewModel, Landing, SessionUiProvider | `pages/Auth/` (Login, ForgotPassword, ResetPassword, ConfirmPassword, TwoFactor, VerifyEmail) and the Timezone and Photo welcome steps under `pages/Welcome`; migrations `000500`–`000503` (security events, user state). No workspace, access or Concierge class runs |
| workspace-ui | workspaces, access, shell, session-ui | 1: WorkspaceUiProvider (its controllers and view models keep their `SessionUi` names) | `pages/Workspace/` Index, Error, Dashboard, Search, Sections, Placeholder, Account, `Settings/Layout.vue`, `Settings/People`, `Settings/Placeholder`, and `pages/Provision/NoWorkspace` (22 files, moved from session-ui byte for byte); since 0.6.0 also `pages/Auth/Invitation/Show` and `pages/Welcome/WorkspaceTimezone` (moved from session-ui byte for byte, with the invitation and demo sign-in routes) |
| mcp | workspaces, access | 6: WorkspaceServer, Context, WorkspaceConnection, ConnectionController, BindMcpConnection, McpProvider | migration `000600_create_kit_mcp_tables` (connections) |

That's 57 library classes plus the composition root `FeaturesServiceProvider`, and 16 generated files: 9 migrations and 7 Vue files. No config, route or `app/` file is generated. Migration names are shortened above; each carries the `0001_01_02_` prefix.

### What the host does by hand

Plans print these, and verify them where the kit can. The kit never writes them (see the README's catalog section for the plan output).

| Feature | Host step or requirement | Checked |
|---|---|---|
| core | npm: `@inertiaui/modal-vue`, `@lucide/vue`, `@tanstack/vue-table`, `reka-ui >=2.11.0`, and `@tiptap/core`, `@tiptap/extension-code`, `@tiptap/extension-placeholder`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/vue-3` `^3.32.0` (InputRichText) | the declared range in `package.json`, and the installed version |
| core | optional: register `ExplanationTypesExtension` in the typescript-transformer config | manual |
| workspaces | `user_model`: the user model implements the Member contract and uses `HasWorkspaces` | by reflection, and it blocks |
| workspaces | `workspace_model`: `concierge.models.workspace` and `multitenancy.tenant_model` name one model that implements the Workspace contract and `IsTenant` and uses `IsKitWorkspace` | by reflection, and it blocks |
| mcp | env: `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` (or keep the `passport:keys` files) | documented in `.env.example`, or `storage/oauth-private.key` / `oauth-public.key` exists (never read) |
| mcp | schedule: `model:prune --model=Tey\Kit\Features\Mcp\Models\McpIntent` | the scheduled events |
| shell | mention: the `@kit/shell` alias in `vite.config.ts` and in `tsconfig.json` paths | the file mentions `@kit/shell`, as attention |
| shell | mention: the `@kit/ui` alias in `vite.config.ts` and in `tsconfig.json` paths (the shell's pages import the kit's components through it) | the file mentions `@kit/ui`, as attention |
| shell | mention: the kit's `Tey.Kit.*` types, `vendor/tey/kit/types/kit.d.ts` in `tsconfig.json`'s `include` | the file mentions `tey/kit/types/kit.d.ts`, as attention (leave it out if your own transformer writes the `Tey.Kit` namespaces) |
| shell | mention: the layout callback in `resources/js/app.ts` imports the kit's PublicShell (`pages/Workspace/Partials/PublicShell`) | the file mentions `PublicShell`, as attention |

`kit:status` checks the adopted features' host steps and npm requirements again, so a step that has since gone unmet shows there too.

With core adopted, `kit:status` also checks that `StartKitRequest` is in the `web` middleware group. Core adds it only when `web` exists as core boots; when it is missing (no `web` group, or one defined later), status reports attention: request memos are off and the kit's Inertia props can be lost after a flush. Define `web` before the kit's providers boot, or add the middleware first in `web`.

Two checks apply to every host, whatever it adopts:

- **Case conflicts** (blocking). A declared path whose segment the host has under another case, such as a Laravel starter-kit app's `resources/js/pages/auth` against session-ui's `pages/Auth`. The plan lists each in `case_conflicts` (the feature, the `declared` path, the `existing` host path and its `kind`), the feature's row is not executable, and a host step names both paths. The kit finds them itself, before Mod plans, so the plan never throws. `kit:add <features> --adopt-case` lists the renames and render-name rewrites without writing. With `--apply`, it renames each directory to the declared case (`git mv` in a git checkout, in two steps so a case-insensitive filesystem takes it), rewrites `Inertia::render('auth/…')`, `inertia('auth/…')` and `Route::inertia(…, 'auth/…')` under `routes/` and `app/Http/`, and stops: nothing is installed. It prints what it changed and the next commands: the usual `kit:add <features>` preview, now executable, and its apply with `--expected-plan`, so the host reviews the plan as for any apply. It refuses a file conflict, and a directory whose declared case already exists beside it. Page names built at runtime, or in other files (Wayfinder output, tests, the frontend), are not rewritten: search for `auth/` after the rename.
- **An ignored `.mod-maintenance/installed/`** (attention, never blocking). The installed lineage records are portable and must be committed, with `.mod-maintenance/installed.json`, or a fresh clone and CI lose the adoption. Only the journals, the lock and `owner.json` (it holds an absolute root) stay local. Replace a `/.mod-maintenance` line with:

  ```gitignore
  /.mod-maintenance/journal-*.json
  /.mod-maintenance/lock
  /.mod-maintenance/owner.json
  ```

  In a git checkout, git decides (`git check-ignore`); elsewhere the root `.gitignore`'s plain lines for `.mod-maintenance` and `.mod-maintenance/installed` are read.

  In a git checkout the same step also fires when `.mod-maintenance/installed/` holds records git does not track (never committed, not ignored): another checkout of the host then has no lifecycle records, and `kit:update` says so. Commit `.mod-maintenance/installed/` from the checkout that ran `kit:add`. `kit:update` names this cause first when it finds kit files but no records, before the `kit:adopt` route.

Not shipped:
- the users table, which the host owns;
- the starter's `LegacyDomainMigration` guard, which is template-only;
- the starter's `FeatureProvider`, which `FeaturesServiceProvider` replaces.

## How a generated file reaches the library

- `FeaturesServiceProvider` is auto-discovered. It finds the adopted features in `config('kit.features')`. When that is null, it reads the `blueprint_features` of the kit's installed record (`.mod-maintenance/installed.json`). An explicit `kit.features` list overrides the lineages, so `kit:add` and `kit:adopt` add each feature they adopt to it, in canonical order. They edit only a literal list of quoted ids in `config/kit.php`, check the result by reading the file back, and undo the edit if it doesn't read back the same. A list they can't edit safely (computed, from `env()`, or a symlinked file) is left alone, and they print the edit to make as attention.
  - An unknown feature, a duplicate, a missing requirement or a malformed record is refused.
  - No record means nothing is adopted.
- For each adopted feature it:
  - registers the feature's provider;
  - loads `database/migrations/kit/<feature>`, so `php artisan migrate` needs no app configuration;
  - adds the feature's contributions to the shared `Registry`: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, the connection guard and error codes.
- Optional integrations register only when both sides are adopted. For example, the settings MCP tools register only with mcp.
- A delegated (MCP) principal is refused unless mcp is adopted.
- Error codes render through the exception handler. session-ui shares its Inertia props (`kitPassword`, `passkeyOffer`, `environment`) from its provider as lazy closures, so they sit beside the host's own `HandleInertiaRequests` shares, and the kit never flushes shared props. Core adds `StartKitRequest` first in `web` on every host that adopts core, with or without session-ui (the request's memos begin and the kit's props are put back before any host middleware or route-model binding); session-ui adds `PrepareKitRequest` (the registry's late, session-dependent hooks) after the host's middleware, through the HTTP kernel, and its plain Inertia middleware only when the host's `web` group has none. The host's `bootstrap/` is never edited.
- **A host's own landing page stays its own.**
  - Session-ui owns only its welcome steps under `pages/Welcome` (`Welcome/Timezone`, `Welcome/Photo`, `Welcome/WorkspaceTimezone`), not the folder, so a host's `Welcome/Page.vue` (or `Welcome.vue`) and its partials are never planned, claimed or touched.
  - The kit's home route is `kit.home` (`KIT_HOME`, `/` by default), served by workspace-ui. Sign-in and the home crumb land there. A person with one workspace sees its dashboard there; a person with several is sent to the workspace they last used, while they are still an active member and may open it, else to the workspace list (`/me/workspaces`). Without workspace-ui the kit serves nothing there: it is the app's own page, and sign-in lands on it. An app that serves its own landing at `/` sets it to another path, such as `/app`: the kit's home, and the breadcrumbs' root, move there, and the app's `/` is left alone.
  - If the app already names its landing `home`, set `kit.home_name` (`KIT_HOME_NAME`), for example `app.home`. Only the kit's home route is renamed, and its breadcrumbs follow it. To rename every kit route instead, set a prefix with `kit.routes.session-ui.name`.
- **session-ui's route groups** can be switched off one at a time, while the rest of its routes stay: `kit.routes.session-ui.<group> => false`. Every group is on by default.
  - `invitations`: the invitation link `invitations/{token}` (show and accept) and the account it creates. People's invite, resend and revoke stay.
  - `welcome`: the welcome steps and the dashboard's welcome card.
  - `passkey-offer`: the post-sign-up passkey offer.
  - `demo-login`: the first-run demo's sign-in.
  - A group that is off has no routes and no names. The kit's own redirects and props for that group are guarded, so nothing links to a route that isn't there: no welcome redirect, card or after-join steps, and no shared passkey offer.
  - An unknown key, or a value that isn't `true` or `false`, stops the boot with an error that lists the groups.
  - **Turning `invitations` off is transitional.** It lets an app that has its own invitation flow at `invitations/{token}`, with the names `invitations.show` and `invitations.accept`, keep that flow while it moves onto the kit's. The kit's invitation emails and People links still point at `/invitations/{token}`, so the app's route must accept the kit's tokens, or the app must override Concierge's invitation URL. `kit:first-run-demo` refuses to run while `invitations` or `demo-login` is off.
- The library's defaults name library classes, for example `multitenancy.tenant_model` is `Tey\Kit\Features\Workspaces\Models\Workspace`.
- Pages are rendered by name with a typed ViewModel (`Inertia::render('Workspace/Note/Index/Page', new IndexNoteViewModel(...))`). Pages type their props from the generated TypeScript of that ViewModel and declare no layout.
- The shell is the `shell` feature's `pages/Workspace/Layout.vue` and its `Partials/`. Shell needs only core, so a host with its own tenancy adopts `core` and `shell` and builds the ShellData itself. Shell shares the `shell` Inertia prop, built by the bound `ShellSource` (`PlainShell` on a core and shell host); session-ui binds its `PageShell`, and nothing shares it again, so there is one producer. `PageShell` builds the `layout` props (`ShellData`): the product, a workspace `switcher` (current and choices), the `navigation` (the primary pages, with Settings as the last, one link to a landing page), the `user` and their menu pages, `links` and `env`. A feature places its page with `Registry::navigation($ability, $label, $suffix, slot:, group:, order:, active:)` (`active` is a path pattern under the workspace, or a list of them where an entry `route:<name pattern>` matches the route name instead); the slots are `primary` and `user-menu`. A feature registers a settings section, not a nav item, with `Registry::settingsSection($scope, $key, $label, $description, $ability, $group, $order)`: `workspace` sections are pages at `/@{workspace}/settings/{key}`, `personal` ones at `/me/{key}`, and session-ui's two landing pages (`/@{workspace}/settings`, `/me`) list the ones a user may open, grouped and in order. Every page route declares a glhd/gretel breadcrumb through `WorkspaceRoutes::page()` (workspace pages, in both URL forms) or `personalPage()`, with its parent (workspace → settings → section; me → section; list → record); gretel shares the trail as the `breadcrumbs` Inertia prop and `Partials/Breadcrumbs.vue` renders it. Run `php artisan breadcrumbs:cache` before caching routes. A workspace's landing is its first primary page. The host's `resources/js/app.ts` (host-owned) picks the layout; the app wraps `Layout.vue`, which renders the header (mark slot, switcher hidden for one workspace, nav, env pill, user menu, and a drawer below `lg`) and takes the app's content, `extra` account-menu items and `footer`. An app with its own module switcher passes the `nav` slot (props `navigation` and `label`): it replaces PrimaryNav at every width, rendered once, and the drawer then lists no pages, so the app no longer needs to keep its own `Layout.vue`. To keep a page list in the phone drawer, pass `drawer-nav` too (same props): the drawer renders it in place of the kit's pages. Without it, the drawer lists the kit's pages, or none when `nav` is used. So notes and settings never import session-ui.

## Config

No feature generates config. Defaults resolve from the library with no file in the app:
- `kit.*`, from the library's `config/kit.php`;
- by feature, the kit's keys over the package's own defaults:
  - workspaces: `concierge`, `multitenancy`, and `permission` (`teams`, `column_names.team_foreign_key`, `cache.store`);
  - access: `pennant`;
  - settings: `settings`;
  - session-ui: `fortify`, `inertia` (`fortify.home` defaults to `kit.home`).

To customise, publish into the app: `php artisan vendor:publish --tag=kit-config` writes `config/kit.php`. For a package, publish the package's own config with its own tag, then apply the kit keys listed in `config/defaults/<package>.php`. A published file is host-owned (`config` is in core's host-owned set). No feature owns it, `kit:update` never touches it, and once it exists it is the whole truth for its key: the kit's defaults for that key stop applying. Cached config is used as it is.

`config/kit.php` holds:
- `features`: the adoption override;
- `models.user`;
- `capabilities`;
- `routes.<feature>`: `enabled`, `prefix`, `middleware`, `name`;
- `invitation_redirect`;
- `mcp.connection_ability`.

## Customising without conflicts

Editing a generated file makes that feature's next kit update a conflict: Mod refuses it, and the app keeps its bytes. Only migrations and Vue files are generated. To avoid a conflict:
- **Config:** edit freely. Config is never generated, and a published config file is the app's own.
- **Vue:** copy the page into your own path and render that name instead, rather than editing `resources/js/pages/**` files the kit generated.
- **Classes:** extend the library class in `app/` and point config at it, for example `multitenancy.tenant_model` or `kit.models.user`.
- **Migrations:** add a new migration of your own; never edit a kit migration.

## Ownership

`core` declares the host-owned set that every selection inherits (it also includes `resources/css/app.css` and `composer.json`):
- `app/Models/User.php`, `app/Domain`
- `bootstrap`, `database/seeders`, `config`
- `routes/web.php`, `routes/api.php`, `routes/console.php`
- `package.json`, `vite.config.ts`, `.env`, `.env.example`

`session-ui` adds `resources/js/app.ts`. No owned path overlaps a host-owned one, and the tests check that.

## Known gaps

- Kit migration ids are new (`0001_01_02_*`), not the starter template's. The template's move onto tey/kit has to map its applied ids.
- The Vue stubs aren't compiled in CI: the kit has no `vue-tsc` or `@vue/compiler-sfc`. The tests check page placement and casing, no `<style>`, no authored class names, no layout imports, and that every relative import stays inside its page folder. TypeScript for the ViewModels is generated in the host.
- Nothing here applies a feature to an app (`kit:add` is kit #2; per-feature lineages are kit #4). Package providers aren't booted in the tests, so the routes, HTTP, MCP and tenant behaviour are covered by the starter's suites once the template adopts tey/kit.

## Lineage record metadata

An installed lineage record (`.mod-maintenance/installed/<hash>.json`) carries the artifact's files and its `metadata`. Besides `feature`, two keys say which files the app keeps:

- `kept`: path => the sha256 of the release bytes the app's edit was made against. `kit:adopt` writes it for a file the app had edited. The file stays out of the lineage until a release changes it; it is then a conflict, and it's claimed once the app takes the release's bytes.
- `held`: path => the sha256 of the kit's bytes the app last saw. `kit:update keep` writes it. The file stays out of every update for good. A plan reports, as attention, when the kit's bytes differ from the recorded hash, and the next apply records the new hash. `kit:update claim` removes the entry and owns the file again, with the app's current bytes as the original.

A path is in at most one of `files`, `kept` and `held`. A kept or held file the app deletes, or the kit drops, leaves the metadata at the next update.

## Kit files and the host's Mod layout

Kit-owned files always use their canonical paths (`app/Kit/`, `resources/js/pages/`, `resources/js/components/Kit/`, `database/migrations/kit/<feature>/` and so on), whatever `mod.layout` the host chooses. `kit:install`, `kit:add`, `kit:adopt`, `kit:update` and `kit:status` write and read the paths in each feature's manifest, never through the host's layout, and the kit generates nothing through `Mod::current()`. A host with its own layout (a modules layout, say) keeps its module tree for its own code. Only the legacy v0.1 `kit:foundation` scaffold places files through the host's layout, so it refuses any layout but `laravel` when it is run. `composer test:layout` checks a layout extending `modules` against `laravel`.
