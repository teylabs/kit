# The starter catalog

tey/kit ships the starter's features as its package catalog, `Catalog::package()`: `core`, `workspaces`, `access`, `settings`, `session-ui`, `mcp` and `notes`, in that canonical order. Each feature has a manifest at `resources/features/<feature>/feature.json` and its stubs beside it. Its identity is pinned in `Catalog::FEATURES` (and checked again in `tests/StarterCatalogCheck.php`).

The v0.1 `foundation` and `defaults` features and the A–D releases are regression fixtures only (`tests/fixtures/v01`, `resources/releases`). They are not in this catalog.

## Library or generated

Under Mod 0.9, any edit to a generated file is a conflict on that feature's next update, even when the update leaves that file unchanged. So the rule is: **library by default; generate only what an app is not expected to edit, or what must live in the app to work at all.**

| Kind | Where | Why |
|---|---|---|
| Classes: models, operations, controllers, middleware, MCP tools, data objects, providers, the registry | Library: `src/Features/<Feature>`, `Tey\Kit\Features\...` | Apps extend them (models, controllers and operations aren't `final`) or configure them. Updates arrive through Composer. |
| Routes | Library: `src/Features/<Feature>/routes/*.php` | Routes are what apps tweak most. A generated route file would block every later update of its feature. Configure them through `kit.routes.<feature>`. |
| MCP views | Library: `resources/views/mcp`, as `kit::mcp.*` | Override them the Laravel way, in `resources/views/vendor/kit/mcp`, which the app owns. |
| Config defaults | Library: `config/kit.php` and `config/defaults/<package>.php` | Apps customise config all the time, so no feature generates any. `FeaturesServiceProvider` merges `kit.php`; each feature's provider lays its package defaults over the package's own (`Core\Defaults`). |
| Migrations | Generated: `database/migrations/kit/<feature>` | Migrations have to exist in the app to be run and recorded. Apps don't edit them. Each is prefixed `0001_01_02_000NXX_`, where N is the feature's place in the requires order (core 1, workspaces 2, access 3, settings 4, session-ui 5, mcp 6, notes 7), so they run after the host's `0001_01_01_*` migrations and a later feature can never reorder foreign keys. |
| Vue pages and components | Generated: `resources/js/pages/<Resource>/<Action>/Page.vue`, with PascalCase folders and `Partials/` for page-local pieces | Unstyled starting points that the app's Vite build compiles: native elements, no authored class names, no `<style>`. Copy them rather than editing them. |

### Per feature

| Feature | Requires | Library classes | Generated files |
|---|---|---|---|
| core | – | 7: Audit, Principal, Registry, Users, Contributes, Defaults, CoreProvider | migration `000100_create_kit_core_tables` (audit_events) |
| workspaces | core | 9: Workspace, TenantBoundary, WorkspaceCache, HasWorkspaces, ProvisionWorkspace, AcceptWorkspaceInvitation, WorkspaceController, IndexWorkspaceViewModel, WorkspacesProvider | migrations `000200_create_kit_workspaces_table`, `000201_create_permission_tables`, `000202_create_kit_operators_table` |
| access | workspaces | 3: Access, ComplimentaryAccess, AccessProvider | migration `000300_create_kit_access_tables` |
| settings | workspaces, access | 10: WorkspaceSettings, WorkspaceProfile, SettingsView, SettingsUi, SettingsController, EditSettingsViewModel, SettingsProvider, Integrations/Mcp ×3 | migrations `000400_create_settings_table`, `000401_create_kit_settings_tables`; `pages/Workspace/Settings/Edit/Page.vue` |
| session-ui | workspaces, access | 6: PageShell, ShellData, HandleInertiaRequests, ErrorViewModel, LoginViewModel, SessionUiProvider | `pages/Workspace/Layout.vue`, `pages/Workspace/Index/Page.vue`, `pages/Workspace/Error/Page.vue`, `pages/Auth/Login/Page.vue` |
| mcp | workspaces, access | 6: WorkspaceServer, Context, WorkspaceConnection, ConnectionController, BindMcpConnection, McpProvider | migration `000600_create_kit_mcp_tables` (connections) |
| notes | workspaces, access | 13: Note, WorkspaceNotes, NoteRecord, NoteChange, NoteController, IndexNoteViewModel, NotesProvider, Integrations/Mcp ×6 | migration `000700_create_kit_notes_tables`; `pages/Workspace/Note/Index/Page.vue`, `pages/Workspace/Note/Index/Partials/NoteEditor.vue` |

That's 54 library classes plus the composition root `FeaturesServiceProvider`, and 16 generated files: 9 migrations and 7 Vue files. No config, route or `app/` file is generated. Migration names are shortened above; each carries the `0001_01_02_` prefix.

Not shipped:
- the users table, which the host owns;
- the starter's `LegacyDomainMigration` guard, which is template-only;
- the starter's `FeatureProvider`, which `FeaturesServiceProvider` replaces.

## How a generated file reaches the library

- `FeaturesServiceProvider` is auto-discovered. It finds the adopted features in `config('kit.features')`. When that is null, it reads the `blueprint_features` of the kit's installed record (`.mod-maintenance/installed.json`).
  - An unknown feature, a duplicate, a missing requirement or a malformed record is refused.
  - No record means nothing is adopted.
- For each adopted feature it:
  - registers the feature's provider;
  - loads `database/migrations/kit/<feature>`, so `php artisan migrate` needs no app configuration;
  - adds the feature's contributions to the shared `Registry`: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, the connection guard and error codes.
- Optional integrations register only when both sides are adopted. For example, the settings and notes MCP tools register only with mcp.
- A delegated (MCP) principal is refused unless mcp is adopted.
- Error codes render through the exception handler, and session-ui adds its Inertia middleware to `web` itself. The host's `bootstrap/` is never edited.
- The library's defaults name library classes, for example `multitenancy.tenant_model` is `Tey\Kit\Features\Workspaces\Models\Workspace`.
- Pages are rendered by name with a typed ViewModel (`Inertia::render('Workspace/Note/Index/Page', new IndexNoteViewModel(...))`). Pages type their props from the generated TypeScript of that ViewModel and declare no layout.
- The shell is session-ui's `pages/Workspace/Layout.vue` and its `Partials/`. `PageShell` builds the `layout` props (`ShellData`): the product, a workspace `switcher` (current and choices), the `navigation` (primary pages, with one current), a `settings` menu, the `user` and their menu pages, `links` and `env`. A feature places its page with `Registry::navigation($ability, $label, $suffix, slot:, group:, order:, active:)`; the slots are `primary`, `settings` and `user-menu`. A workspace's landing is its first primary page. The host's `resources/js/app.ts` (host-owned) picks the layout; the app wraps `Layout.vue`, which renders the header (mark slot, switcher hidden for one workspace, nav, settings menu, env pill, user menu, and a drawer below `lg`) and takes the app's content, `extra` account-menu items and `footer`. So notes and settings never import session-ui.

## Config

No feature generates config. Defaults resolve from the library with no file in the app:
- `kit.*`, from the library's `config/kit.php`;
- by feature, the kit's keys over the package's own defaults:
  - workspaces: `concierge`, `multitenancy`, and `permission` (`teams`, `column_names.team_foreign_key`, `cache.store`);
  - access: `pennant`;
  - settings: `settings`;
  - session-ui: `fortify`, `inertia`.

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
