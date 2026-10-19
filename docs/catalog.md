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
| Migrations | Generated: `database/migrations/kit/<feature>` | Migrations have to exist in the app to be run and recorded. Apps don't edit them. They keep the starter's migration ids. |
| Vue pages and components | Generated: `resources/js/starter/<feature>` | Starting points that the app's Vite build compiles. Copy them rather than editing them. |

### Per feature

| Feature | Requires | Library classes | Generated files |
|---|---|---|---|
| core | – | 7: Audit, Principal, Registry, Users, Contributes, Defaults, CoreProvider | migration `create_starter_core_tables` (audit_events) |
| workspaces | core | 8: Workspace, TenantBoundary, WorkspaceCache, HasWorkspaces, ProvisionWorkspace, AcceptWorkspaceInvitation, WorkspaceController, WorkspacesProvider | migrations `0001_01_01_000000_create_starter_identity_tables` (workspaces only), `0001_01_01_000001_create_permission_tables`, `create_starter_workspaces_tables` (operators) |
| access | workspaces | 3: Access, ComplimentaryAccess, AccessProvider | migration `create_starter_access_tables` |
| settings | workspaces, access | 9: WorkspaceSettings, WorkspaceProfile, SettingsView, SettingsUi, SettingsController, SettingsProvider, Integrations/Mcp ×3 | migrations `0001_01_01_000002_create_settings_table`, `create_starter_settings_tables`; `pages/Settings.vue` |
| session-ui | workspaces, access | 4: PageShell, ShellData, HandleInertiaRequests, SessionUiProvider | `layouts/WorkspaceLayout.vue`, `pages/Auth/Login.vue`, `pages/Error.vue`, `pages/Workspaces.vue` |
| mcp | workspaces, access | 6: WorkspaceServer, Context, WorkspaceConnection, ConnectionController, BindMcpConnection, McpProvider | migration `create_starter_mcp_tables` (connections) |
| notes | workspaces, access | 12: Note, WorkspaceNotes, NoteRecord, NoteChange, NoteController, NotesProvider, Integrations/Mcp ×6 | migration `create_starter_notes_tables`; `pages/Notes.vue`, `components/NoteEditor.vue` |

That's 49 library classes plus the composition root `FeaturesServiceProvider`, and 16 generated files: 10 migrations and 6 Vue files. No config, route or `app/` file is generated.

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
- Vue pages are rendered by name (`Settings`, `Notes`, `Error`…). The host's `resources/js/app.ts` (host-owned) resolves them from `resources/js/starter/**`.

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
- **Vue:** copy the page into your own path and render that name instead, rather than editing `resources/js/starter/**`.
- **Classes:** extend the library class in `app/` and point config at it, for example `multitenancy.tenant_model` or `kit.models.user`.
- **Migrations:** add a new migration of your own; never edit a kit migration.

## Ownership

`core` declares the host-owned set that every selection inherits:
- `app/Models/User.php`, `app/Domain`
- `bootstrap`, `database/seeders`, `config`
- `routes/web.php`, `routes/api.php`, `routes/console.php`
- `package.json`, `vite.config.ts`, `.env`, `.env.example`

`session-ui` adds `resources/js/app.ts`. No owned path overlaps a host-owned one, and the tests check that.

## Known gaps

- `settings` and `notes` ship their Vue pages even without `session-ui`. Those pages import `session-ui`'s layout, so a host compiles them only once session-ui is adopted.
- A route `prefix` doesn't move the controllers' `/workspaces/...` redirects yet (kit #12).
- The Vue stubs aren't compiled in CI: the kit has no `vue-tsc` or `@vue/compiler-sfc`. The tests check their blocks and that every relative import resolves.
- Nothing here applies a feature to an app (`kit:add` is kit #2; per-feature lineages are kit #4). Package providers aren't booted in the tests, so the routes, HTTP, MCP and tenant behaviour are covered by the starter's suites once the template adopts tey/kit.
