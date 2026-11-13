> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

# tey/kit

Feature kit for tenant-aware Laravel apps, built for the Tey Labs fleet. The features (core, workspaces, access, settings, shell, session-ui, workspace-ui, mcp, notes, finalize and deliver) run from the package: an app turns on the ones it lists in `kit.features` in its `config/kit.php`, and their migrations load from the package. The package also carries the feature catalog: each feature's manifest, pinned identity and stubs. Runtime tenancy/membership/settings stay in maintained packages and host bindings. Since 0.18 there is no install or update tooling: the files a feature wrote into an app are the app's own and stay as they are.

## Quick start

```sh
composer require tey/kit
php artisan vendor:publish --tag=kit-config
```

List the features in `config/kit.php`. They register in catalog order whatever order you list them in, and each one's requirements must be listed too (a list with session-ui gets shell):

```php
'features' => ['core', 'workspaces', 'access'],
```

With `kit.features` unset or empty the kit registers no feature and logs an error. Then migrate:

```sh
php artisan migrate
```

Each listed feature's migrations load from the package (`database/migrations/<feature>` in the kit). A copy in the app's `database/migrations/kit/<feature>` under the same name, or under a stamped `<stamp>_<order>_<name>.php` name, loads in place of the package's, so an edited copy stays the one that runs.

`workspaces` also needs your user model to be a workspace member. `app/Models/User.php` is yours, so kit never writes it: add the lines yourself.

```php
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;

class User extends Authenticatable implements Member
{
    use HasWorkspaces;
}
```

`kit.models.user` (else the `users` auth provider's model) and `concierge.models.user` must name that model.

A feature's pages render from files in the app (`resources/js/pages/...`), whose stubs ship under `resources/features/<feature>`. 0.18 writes none of them; a later release adds a way to generate a feature's files.

**Existing apps.** On 0.17.8, run `php artisan kit:detach` first and commit the `config/kit.php` it writes, then follow the changelog's 0.18 "Upgrading" steps.

Recipes and guides:

- [Public site](docs/public-site.md): core, shell and session-ui with the PublicShell, registration off, no workspaces, workspace pages or invitations.
- [Deliver](docs/deliver.md): the customer link's 404 and 410 answers, the expired page, and the preview's document.
- [Upgrading from kit 0.2.x](docs/upgrading-from-0.2.md): historical, for the 0.2 tooling.
- [The host's repository](docs/host-repository.md): setting up a fresh checkout or CI, and the `.mod-maintenance` files 0.18 no longer reads.

## Fonts

An app picks one font set by name. Put the component in your layout head:

```blade
<head>
    <x-kit::fonts />
</head>
```

It links the set's fonts from fonts.bunny.net and sets four variables on `:root`: `--font-sans` (text), `--font-serif` (display), `--font-mono` and `--font-display-weight`, each with system fallbacks. Pages and the Tailwind theme use only those variables. Tailwind v4's `font-sans`, `font-serif` and `font-mono` read them already, so don't set font families anywhere else. For display text, use `font-serif` with `font-weight: var(--font-display-weight)`.

Under a Content Security Policy, every tag the component writes carries a nonce: `<x-kit::fonts nonce="{{ $nonce }}" />`, or, without the attribute, the one your app set with `Vite::useCspNonce()`. With neither, no nonce attribute is written.

| Set | Text (`--font-sans`) | Display (`--font-serif`) | Mono (`--font-mono`) |
| --- | --- | --- | --- |
| `instrument` (default) | Instrument Sans 400/500/600 | Instrument Serif 400 and italic, regular | IBM Plex Mono 400/500 |
| `instrument-inter` | Inter 400/500/600 | Instrument Sans 600, semibold | IBM Plex Mono 400/500 |
| `inter-serif` | Inter 400/500/600 | Instrument Serif 400 and italic | IBM Plex Mono 400/500 |
| `hanken` | Hanken Grotesk 400/500/600 | DM Serif Display 400, regular | DM Mono 400/500 |

To switch sets, set `KIT_FONTS` in `.env`, or `fonts` in a published `config/kit.php`:

```sh
KIT_FONTS=hanken
```

An unknown set name is refused when the component renders, and the error lists the four sets.

### Self-hosted fonts

By default the faces come from fonts.bunny.net. To serve them from your own origin instead, with no third-party request at runtime, set `KIT_FONTS_SOURCE=self`. Pages then work offline, and scripts can read the page's own stylesheet rules. Then publish the files:

```sh
KIT_FONTS_SOURCE=self
php artisan vendor:publish --tag=kit-fonts --force
```

- **What's published:** the kit ships every set's faces as woff2 files, in the same subsets bunny serves, in `resources/fonts`. Publishing copies them to `public/vendor/kit/fonts`. The component then writes one `<style>`: an `@font-face` per face of the chosen set, with `asset()` URLs, then the same `:root` variables.
- **Licences:** every family is under the SIL Open Font License. The licences ship in `resources/fonts/licenses`.
- **Upgrading:** run the publish command again (with `--force`) after a kit upgrade that changes the files. Alternatively, commit `public/vendor/kit/fonts`.
- **Files missing:** if they haven't been published, the component throws in local and testing. Elsewhere it falls back to the system fonts, never the CDN, and `kit:preflight` warns.
- **Maintainers:** after changing `Fonts::SETS`, run `php bin/fonts-fetch.php` to refresh the files, `faces.json` and the licences. `FontsCheck` fails until every set's faces are shipped.

## Features

The app is a set of features. Each feature has a manifest at `<catalog>/<id>/feature.json` with its id, owned paths, generated files (with their stubs), requirements (other features and packages), conflicts and host-owned paths. An optional `host_steps` names changes only the host can make. The kit never makes them, and since 0.18 no kit command checks them: the manifest is the list to work through by hand.

- `user_model`: the user model must implement `implements` and use the trait `uses`.
- `workspace_model`: `concierge.models.workspace` and `multitenancy.tenant_model` must name one Eloquent model that implements `implements` and spatie's `IsTenant` and uses the trait `uses`. See [Adopting into an existing workspaces table](#adopting-into-an-existing-workspaces-table).
- `env` (`[{key, why, or_file?}]`): the key must be set in each environment and documented in the host's `.env.example`. With `or_file`, that file (a secret kept as a file, such as `storage/oauth-private.key`) does instead.
- `provider` (`[{class, why}]`): the service provider must be registered.
- `schedule` (`[{command, why}]`): a scheduled event must run the command, in any form and wherever the schedule lives (routes/console.php, bootstrap/app.php's `withSchedule`, a Console Kernel).
- `config` (`[{key, not, why}]`): the config value at `key` must not be `not`, in each deploy environment.
- `theme` (`[{file, why}]`): the host's stylesheet (core declares `resources/css/app.css`) imports the kit's `kit.css`, which carries the colour tokens' defaults, or defines every semantic token the kit's components use itself. The token can be a plain variable (`--primary:`) or a theme mapping (`--color-primary:`). `kit.css` imported from a second Vite entry counts.
- `mention` (`[{file, contains, why}]`): the host file must mention `contains`. A step on `vite.config.ts` means whichever Vite config the app has (`.ts`, `.js` or `.mjs`), and a step on a script under `resources/js` (`app.ts`) is met by any script entry the Vite config names.

A `requires.npm` entry names a package and range the host's `package.json` must declare; the kit installs nothing. An optional `optional` list (`[{id, title, do}]`) names integrations a host may add. `<catalog>/blueprint.json` holds what every selection shares: the artifact name, version, groups, migration order and verification policy.

**Colour tokens.** The kit's components are coloured with shadcn-vue's semantic tokens (`bg-primary`, `text-muted-foreground`, `border-input`, `ring-ring` and the rest). `kit.css` defines them all at the lowest precedence, so a host with no theme still renders the kit in colour, and any host theme wins:
- an `@theme default inline` block maps each `--color-*` to its plain variable; a host's own `@theme` replaces it;
- the starter kit's neutral values sit on `:where(:root)` and `:where(.dark)`, which any `:root` or `.dark` of the host overrides, whatever the import order.
- status text on a surface has its own tokens, beyond shadcn-vue's: `--destructive-text` (an error, a destructive menu item), `--success-text` (FormActions' "Saved"), `--warning-text` and `--info-text` (an Alert's icon), as `text-destructive-text`, `text-success-text` and so on. `--destructive-foreground` stays text on a destructive background. The kit's values meet WCAG AA (4.5:1) on background, card, popover, accent and muted, light and dark; a host theme that sets its own colours should set these too. No kit stub colours text with a raw palette colour (`text-green-600`).

Import it after Tailwind: `@import './kit.css';`.

**Host UI primitives (`requires.host_ui`).** The kit's components import a few UI primitives from the host's `@/components/ui` (shadcn-vue's): core declares `button`, `checkbox`, `dropdown-menu`, `input`, `label`, `sheet` and `spinner/Spinner.vue`. A name is met by `resources/js/components/ui/<name>/index.ts`, and a `<name>/<File>` entry by that file. A missing one is a broken build: add it with shadcn-vue, for example `npx shadcn-vue@latest add button checkbox dropdown-menu input label sheet spinner` (only the missing ones). The kit's library check fails when a stub imports a primitive its feature does not declare. The kit has its own `cn` (`resources/js/components/Kit/utils.ts`, clsx and tailwind-merge), so nothing depends on the host's `@/lib/utils`.

**`@inertiajs/core`.** `@inertiajs/vue3` pins `@inertiajs/core` exactly, so core requires `@inertiajs/vue3` and not `@inertiajs/core`. No kit stub imports `@inertiajs/core` (the library check enforces it): the few Inertia types it needs are its own, in `resources/js/components/Kit/types.ts`. A host `package.json` that also declares `@inertiajs/core` at another version than vue3 pins (an exact match is fine), or a `node_modules` holding two copies of it, is a problem: two copies' types disagree, and PageProps augmentation stops applying. Remove the host's `@inertiajs/core` line (or set it to vue3's exact version).

The package catalog, `Catalog::package()`, is the starter catalog: core, workspaces, access, settings, shell, session-ui, workspace-ui and mcp, plus the opt-in notes ([docs/catalog.md](docs/catalog.md)). The v0.1 features `foundation` (the policy file) and `defaults` (the defaults file, which requires `foundation`) are a regression fixture in `tests/fixtures/v01`, loaded by `tests/fixtures/v01.php`; they are not in the package catalog. The whole app is every feature. Select features with `Blueprint::features(['foundation'], $catalog)` or `Blueprint::all($catalog)`.

A selection is checked before anything is built. It is refused when a feature is unknown or listed twice, a required feature is missing, two features conflict, a file sits outside its feature's owned paths or inside a host-owned path, two features own the same path, or two features disagree on a package constraint.

Each feature is content-addressed: its manifest bytes and its stub bytes are pinned in `Blueprint`. A selection's source reference covers the composed manifest, every resource byte and every feature's pinned identity. The order you list features in does not change it. The selection is recorded in the manifest and in the artifact metadata (`blueprint_features`).

### Adopting into an existing workspaces table

A host app that already has its own `workspaces` table, its own workspace model and spatie/laravel-permission's tables (with teams) adds the `workspaces` and `access` features without a second workspaces table or second permission tables.

1. **Bind your model.** Publish `config/concierge.php` and `config/multitenancy.php`, and set `models.workspace` and `tenant_model` to the same class. The model extends spatie's `Tenant` (or implements `IsTenant`), implements `Tey\Concierge\Contracts\Workspace` and uses `Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace`, which carries the kit's slug hooks, `memberOf` scope, `conciergeName()`, `conciergeAcceptsMembers()` (from `admitted`) and `required()`. The kit creates workspaces with `name`, `admitted` and `provisioning_invitation_id`, so those must be fillable. The two keys must name the same class, and the model needs the contract, `IsTenant` and the trait (the `workspace_model` host step).

   ```php
   use Spatie\Multitenancy\Models\Tenant;
   use Tey\Concierge\Contracts\Workspace as ConciergeWorkspace;
   use Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace;

   class Workspace extends Tenant implements ConciergeWorkspace
   {
       use IsKitWorkspace;

       protected $guarded = [];
   }
   ```

2. **Tell the kit the tables are yours.** Set `kit.workspaces.adopt` and `kit.access.adopt` to true (`KIT_WORKSPACES_ADOPT` and `KIT_ACCESS_ADOPT`, or your own `config/kit.php`). The workspaces and permission migrations then adopt your tables in place and refuse, rather than create, a table that doesn't exist yet. Null, the default, adopts only a table the database already has and creates a missing one. The permission migration refuses permission tables that exist only in part, or that lack the configured teams column (`permission.column_names.team_foreign_key`, `workspace_id` by default) while `permission.teams` is on.
   **Fresh databases.** On an empty database (CI, a new developer, tests) your own `workspaces` and permission migrations must run before the kit's. An app that adopted under an earlier kit has stamped copies in `database/migrations/kit/<feature>` (`<stamp>_<order>_<name>.php`, one second after its latest migration then): keep them. They load in place of the package's migrations of the same name, so `migrate` runs your own migrations first, then the kit's, and ends with the same schema as adopting over an existing database. A migration a later release adds keeps the package's name. Without stamped copies the package's `0001_01_02_*` migrations sort before your own, and with `kit.workspaces.adopt` on the workspaces migration refuses until your own has run.
3. **Migrate.** The workspaces migration adds only the columns the kit needs and the table lacks: `admitted` (default true; every existing row is set to true), `settings_version` (default 1; every existing row is set to 1), `provisioning_invitation_id` (nullable, unique) and `slug` (filled from the name for each row without one). It never drops or alters a host column. The permission migration creates nothing when all five spatie tables exist.
4. **Seed operators before deploy.** With workspaces, tenant admin (`concierge.manage-tenants`) is a row in `operators` on a verified, active account. Concierge's default, the `concierge.tenant_invitations.platform_owners` emails, no longer applies, and `operators` starts empty, so without a seed the platform owner loses tenant admin on deploy. `php artisan kit:operators:seed --from-platform-owners --dry-run` lists what it would seed; without `--dry-run` it adds an operator for each platform-owner email whose user (matched on the trimmed, lower-cased `email` column) is verified and active. It is idempotent, and reports already-operators, skipped users (unverified or inactive) and emails with no user. While platform owners are configured and `operators` is empty, boot logs a warning in `local` and `testing` only (production boot runs no query for it). The warning shows addresses masked (`j***@example.com`) with counts; only an interactive `--dry-run` shows unmatched emails in full, so you can fix them. The seed and the warning read `config('concierge.tenant_invitations.platform_owners')`, never the environment directly, so a cached config decides. `composer test:operators-seed` covers it on SQLite.

Kit code reaches the workspace only through `Tey\Kit\Features\Workspaces\Workspaces` (`model()`, `query()`, `current()`, `required()`), and its type hints name the Concierge contract, so every kit path returns your model. `Models\Workspace` stays the default for kit hosts, and nothing changes for them.

Rolling back: each adoption is recorded when it happens, in a kit-owned `kit_adoptions` table (the table name, the columns the kit added, when), which the first adoption creates and a fresh kit install never has. The kit's `down()` migrations read that record: an adopted workspaces table keeps its rows and every host column, and loses only the columns the record says the kit added (then the record goes); adopted permission tables are kept whole. A table the kit created is dropped as before. Renaming or dropping host columns later changes nothing about this.

**Rolling everything back on an adopting host (`migrate:reset`, or `migrate:refresh`).** The kit's `000204` and `000207` put a foreign key from `audit_events.workspace_id` onto your own `workspaces` table. A reset runs every migration's `down()` in one batch, newest file first, so your `create_workspaces` migration's `down()` runs before the kit's, and dropping the table fails on that key. The kit's own `down()`s come too late to help. Drop the key first in your migration's `down()`:

```php
public function down(): void
{
    // The kit's audit_events key onto this table (000204/000207): dropped first, or the table can't be dropped. SQLite can't drop a key by name, and
    // doesn't need to: it drops the table and the key's ON DELETE SET NULL clears the column.
    if (Schema::hasTable('audit_events') && Schema::getConnection()->getDriverName() !== 'sqlite') {
        foreach (Schema::getForeignKeys('audit_events') as $key) {
            if ($key['columns'] === ['workspace_id'] && $key['foreign_table'] === 'workspaces') {
                Schema::table('audit_events', fn (Blueprint $table) => $table->dropForeign($key['name']));
            }
        }
    }
    Schema::dropIfExists('workspaces');
}
```

Some things a rollback leaves in place, on purpose:

- **The audit key migrations are forward-only.** `0001_01_02_000204` to `000207` have no-op `down()`s. After a rollback, `audit_events.workspace_id` stays nullable and keeps its `ON DELETE SET NULL` key. Making the column required again would fail on rows whose workspace was deleted, and restoring a key without `SET NULL` would block deleting those workspaces again. Only `000208`'s `workspace_name` column is dropped.
- **The access seed survives.** Rolling back `access` drops `permission_defaults`, the record of which default grants `kit:permissions:sync` applied. The permissions it created (such as `workspace.members.manage`) and their `role_has_permissions` rows for owner and admin stay, because your app may have assigned them since. To remove them by hand, delete the permissions you no longer want from spatie's `permissions` table (their `role_has_permissions` and `model_has_permissions` rows go with them by cascade), then run `php artisan permission:cache-reset`.

`migrate --pretend` on an adopting host, before the kit's migrations run, lists the adoption's statements (the columns added to your `workspaces` table and the `kit_adoptions` record) instead of refusing. Pretend runs no query, so the migrations can't see that your tables exist, and with `kit.workspaces.adopt` or `kit.access.adopt` on they take the adoption path. With the flags off it shows the statements that create the tables.

Not covered: `composer test:workspace-adopt` runs on SQLite memory and, in CI's separate PostgreSQL job, against a PostgreSQL service container (`DB_CONNECTION=pgsql` with the `DB_*` variables; it drops and recreates the `public` and `kit_second` schemas, so point it only at a throwaway database). `composer test:migrations` checks that a stamped copy keeps its package twin out. There is no MySQL run and no end-to-end run against a real host app.

### Adopting existing access and settings tables

A host may already have the access feature's `entitlements` or `complimentary_grants` table, or spatie/laravel-settings' own table (`settings.repositories.database.table`, `settings` by default). The kit never creates over one. The migration adopts a compatible table in place: it creates, adds and changes nothing on it, records it in `kit_adoptions`, and creates only the tables the host lacks (for settings, `settings_intents`). It refuses an incompatible table before creating anything, with each problem named. A table is compatible when:

- every column the kit reads or writes exists, in a compatible type family (integer, string, time, boolean, json; on SQLite a json column is text): `entitlements` needs `id`, `workspace_id`, `kind`, `revoked_at`; `complimentary_grants` needs `id`, `workspace_id`, `granted_by`, `intent_key`, `fingerprint`, `revoked_by`, `revocation_reason`, `reason`, `expires_at`, `revoked_at`; the settings table needs `id`, `group`, `name`, `locked`, `payload`, `created_at`, `updated_at`;
- `id` auto-increments, and each column the kit writes null into (`revoked_at`, `revoked_by`, `revocation_reason`, the timestamps) is nullable;
- the unique index the kit relies on exists, on the same columns in any order: `(granted_by, intent_key)` on `complimentary_grants`, `(group, name)` on the settings table;
- on a table the kit inserts into (`complimentary_grants`, the settings table), every other host column is nullable, has a default or auto-increments, so the kit's inserts succeed. Other host columns and indexes are kept as they are.

A table whose schema the kit cannot read is a refusal, never a create. Rolling back keeps an adopted table and its rows, and drops only the record. On a fresh database, the kit's `0001_01_02_*` migrations sort before your own, so the access migration would create `complimentary_grants` before yours does unless the app keeps stamped copies (above, for hosts that adopted their `workspaces` or permission tables); run your own migration first there. `composer test:table-adopt` covers it on SQLite and in CI's PostgreSQL job (its own `kit_table_*` schemas, dropped and recreated, so point it only at a throwaway database).

### Row-level security (PostgreSQL)

An opt-in second tenant lock under the app's workspace scoping (`KIT_RLS=true`, `kit.tenancy.rls`). Workspace tables carry a policy that matches `workspace_id` against a transaction-local setting (`set_config(..., true)`, never a session `SET`). Each workspace request and each queued job runs in one transaction that carries its workspace id, and a job with no workspace fails closed. The app connects as a `NOBYPASSRLS` runtime role (`php artisan kit:rls:role`) that owns no table; migrations run on a separate admin connection. `Rls::protect('table')` protects a table in a migration, and `kit:preflight` fails when the runtime role bypasses RLS or owns a table, or when a workspace table is unprotected. PostgreSQL only: on other drivers it is a no-op and the database enforces nothing. See [docs/rls.md](docs/rls.md) for setup, Laravel Cloud, pooler caveats and what isn't covered.

## Feature library

The starter's features run from the library under `src/Features/<Feature>` (`Tey\Kit\Features\...`): core, workspaces, access, settings, shell, session-ui, workspace-ui, mcp and notes. `Tey\Kit\Features\FeaturesServiceProvider` (auto-discovered) registers the providers of the features `config('kit.features')` lists, loads their migrations from the package's `database/migrations/<feature>` (an app's copy in `database/migrations/kit/<feature>` wins by name), and fills the `Registry` they share: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, the connection guard and error codes. A selection with an unknown feature, a duplicate or a missing requirement is refused.

Routes and config ship in the library: no feature generates a config file. The kit's defaults merge in with nothing published; `php artisan vendor:publish --tag=kit-config` writes `config/kit.php` as the app's own file, which the kit never writes to. `kit.routes.<feature>` takes `enabled`, `prefix`, `middleware` and `name` (a route-name prefix). Other keys: `kit.models.user` (default: the `users` auth provider's model), `kit.capabilities` (capability => on), `kit.invitation_redirect` (a route name taking `workspace`; default: the first navigation link) and `kit.mcp.connection_ability` (default `settings.read`). A delegated (MCP) principal is refused unless mcp is adopted. Controller redirects still use `/workspaces/...` paths, so a route `prefix` does not move them yet (kit #12).

### Capabilities and Pennant

Declare a capability from your provider's `register()` (`app(Registry::class)->capabilities([...])`), with the rest of its contributions. The kit defines each declared capability as a Pennant feature once every provider has booted, so one declared in `boot()` is defined too, but `register()` is the documented place. A capability your app already defined itself (`Feature::define()` in a provider's `boot()`, to follow its own workspace features, say) keeps your definition: the kit defines only the capabilities still undefined.

A capability's default comes from `kit.capabilities`, resolved through Pennant, and Pennant **stores** what it resolved for each workspace (the `features` table on the database driver). Changing `kit.capabilities` later does not change a workspace that already resolved it: run `php artisan pennant:purge <capability>` (or `pennant:purge` for all) after the change, for example in the deploy that ships it. Operator overrides (`kit:capability`, `capability_overrides`) are not in Pennant and survive a purge. The kit does not purge for you: a value your app set itself with `Feature::for($workspace)->activate()` (a plan, say) lives in the same rows and a purge would drop it.

## External catalogs

`Catalog::package()` is this package's own catalog. A consuming package can ship its own. A `Catalog` is a directory with a `blueprint.json` and a `<feature>/feature.json` per feature, plus the pinned identity of each:

```php
$catalog = Catalog::at($root, [
    'core' => 'sha256:…',
    'settings' => 'sha256:…',
], basePin: 'sha256:…');

Blueprint::features(['core', 'settings'], $catalog);
Blueprint::all($catalog);
```

The pins list the features in canonical order. Every check that applies to the package catalog applies to an external one: a wrong pin, a changed manifest, a changed stub, a stub added to or removed from a tree, or a changed `blueprint.json` is refused with the same identity error. A catalog pins its own features only; features of other catalogs are not available in it. Release fixtures A–D stay package-only.

A feature can list its files, or point at a tree of stubs, or both:

```json
"files_from": {"stubs": "stubs", "target": "app/Notes"}
```

The tree is expanded the same way every time: files sorted by relative path, each target is `target` plus the relative path with a trailing `.stub` dropped, and ownership is `generated`. An optional `scaffold` key sets the scaffold (default `kit`). Explicit `files` entries come first. The result is the same map you would get by listing every file. Dotfiles and dot directories are skipped, unless you list one in `files`. A symlink, anything that is not a regular file, a path with `..`, an absolute path, a target outside the feature's `owns`, or two stubs for one target is refused. The feature's identity hashes the manifest and the bytes of every expanded stub.

A–D stay as release fixtures. They are separate regression evidence, not feature sets: their stub bytes carry their own versions (0.1.0 to 0.4.0). Their manifests use the `groups` key, and their pinned identities changed with it (see the changelog).

Generated paths and host-owned branding/domain/configuration/data are distinct in the blueprint. The source reference content-addresses the manifest plus all resource bytes; baseline resources remain available for comparison.

The original A/B resource bytes and source references are pinned and retained. New C/D fixtures represent logical consumer baseline/target versions 0.3.0/0.4.0; both policy and defaults resources change.

Run `php tests/CatalogCheck.php <autoload.php>` for external catalogs and stub trees, against the fixture catalog in `tests/fixtures/catalog`. Run `php tests/FeatureCheck.php <autoload.php>` for manifest validation and feature growth rules. `php tests/ResourceCheck.php <autoload.php>` tampers only fresh disposable package copies. Run `php tests/StarterCatalogCheck.php <autoload.php>` for the starter catalog: pins, the requires graph, stub compilation, ownership and migration loading. Run `php tests/LibraryCheck.php <autoload.php>` for the feature library: its dependency graph against the starter's, autoloading, and the registry each selection builds. CI runs all of these on PHP 8.4 and 8.5. These are synthetic fixtures, not consumer operation/tenant/browser/database acceptance.

The tests install the blueprint's peer packages as dev dependencies, so the compatibility check runs on real released versions, including `tey/concierge` ^0.7.3 with Mod 0.9.

## The shell

The `shell` feature needs only core. It generates the signed-in shell, `pages/Workspace/Layout.vue` with its `Partials/`, and the guest shell, `Partials/GuestShell.vue`. A host with its own tenancy adopts `core` and `shell` and builds a `Tey\Kit\Features\Shell\Data\ShellData` itself. The shell feature shares the `shell` Inertia prop on every page, built by the bound `Tey\Kit\Features\Shell\ShellSource`: on a core and shell host, `PlainShell` (the product and the footer, plus the signed-in user's name, with no workspace), so the PublicShell's footer and CTA get their data without session-ui; with session-ui, its `PageShell`. Nothing in the kit flushes Inertia's shared props or shares `shell` again, so the prop has one producer, beside the host's own `HandleInertiaRequests` shares. A host that builds its own binds its own `ShellSource`. The host's one layout callback picks the shell: GuestShell for auth pages and whenever the shared user is null, `Layout.vue` otherwise. Pages declare no layout.

- **Layout slots.** `mark`, `extra`, `actions` (in the header, and in the drawer below `lg`), the default slot and `footer`, plus `bar`. The `bar` slot is for a compact control that must stay in the bar at every width, such as a search opener. It renders once, never in the drawer, and it shrinks before the switcher and the menu button do. Pass it through your layout wrapper rather than teleporting into the shell's markup: the shell's selectors are not an API.
- **Module tabs.** A module's sibling pages show as PageTabs at the top of the page, under the breadcrumb bar and above the heading, the current one marked. A feature registers them with `Registry::modulePage($module, $ability, $label, $suffix, order:, active:)`, where `$module` is the suffix of the module's primary navigation link. A module owns that prefix and its pages sit under it (`bookings/calendar` for module `bookings`); in local and testing a page outside it logs a warning that suggests the nested suffix, while production stays silent and nothing throws. `active` (here and on `navigation()`) is one path pattern under the workspace or a list of them, for a module that spans several prefixes; an entry `route:<name pattern>` (`route:trips.entries.*`) matches the current route's name instead, and a link is current when any entry matches. The server puts them in the page's typed `layout.navigation` (`PageLayout::$navigation`, one `NavGroup` named for the module): only the pages the viewer may open (a page behind an ability they lack is not in the props at all), in order, the most specific match current. A page outside a settings section gets them while one of the module's pages is current; a ViewModel that sets its own navigation replaces them. No component reads the Registry or the shell for them.
  - **Who draws them.** A top-level `<Page>` in the shell draws its `layout.navigation` as PageTabs. It draws none when it is nested in another `<Page>`, inside SettingsFrame (whose sidebar is that navigation), or when the page renders its own `<PageTabs>` anywhere inside it: that PageTabs claims the row, so there is only one. Placing them in `<Page>` and not the Layout keeps them in the page's column, keeps the one-trail and nested-Page rules in one component, and lets a record frame claim them.
  - **Module tabs and record tabs.** A record's sections are PageTabs too, from the record ViewModel's own `layout.navigation`, drawn inside the record frame under its header (as a case page with tabs does). The record's navigation replaces the module's, and the module tabs show only while one of the module's own pages is current, so a record page never gets two rows. The trail bar leads back to the module.
  - **The SubNav strip.** `<Layout sub-nav="strip">` keeps the old strip of links under the header, from `ShellData::$subNavigation`, and `<Page>` then draws no tabs. It scrolls sideways on a phone. It is not the "Back to parent" strip (`layout.navigationHeader`), and the two can sit together.
- **GuestShell.** Props: `title`, `description`, `width` (`narrow` or `wide`), `backUrl` and `backLabel` (the labelled way back to your site; the shell guesses no URL), `appearance` (the light/dark switch, on by default) and `product` (else the shared `shell.product`). Slots: the card body, `footer` and `mark`. It needs no ShellData, user, workspace or auth route, so it also serves a cacheable public page or an error page that keeps its real status.
- **Types.** ShellData and ShellKind moved from `SessionUi\Data` to `Shell\Data`. The old PHP names are class aliases, and shell generates `resources/js/types/kit-shell.d.ts` aliasing the old TypeScript names, so a layout typed against either still checks.
- **Hosts from before the split.** The shell's files were session-ui's before 0.3. An app that has them keeps them as they are: they are the app's own. A `kit.features` list that names session-ui without shell still gets shell. A feature's manifest lists the paths an earlier version owned under `moved` (`{path, to}`) and `removed` (`{path, policy}`, with `delete-if-unchanged` or `keep-as-host`).
- **Replacing the user menu or switcher.** The Layout imports `UserMenu` and `WorkspaceSwitcher` only through `@kit/shell`, an alias for the generated `resources/js/pages/Workspace/Partials/index.ts`. Add it beside `@kit/ui` (`'@kit/shell': path.resolve(__dirname, 'resources/js/pages/Workspace/Partials/index.ts')`, and the same in `tsconfig.json` paths). To replace one, point the alias at your own module that re-exports the rest.
- **Tokens.** The shell's colours (`--shell-bar`, `--shell-subbar`, `--shell-band`, `--shell-mark-*`) come from the host's `resources/css/app.css`, as in the starter. A host without them sets them there. `--shell-ground` is the Layout's page ground: unset, it is the theme's background.
- **Layout options.** Each is off by default unless noted.
  - `toaster` (on by default): `false` leaves the toasts to your own root shell's Toaster.
  - `documentTitle` (on by default): `false` leaves the `<title>` to your pages' own `<Head>`.
  - `fill`: an editor page at the viewport's height, with no page scroll and an unpadded content area that fills what the header leaves.
  - `wordmarkLabel` names the wordmark link, for example with a tenant's name. Without it the product's name does.
  - `guestHeader` draws the header for a guest too, with no user menu and no drawer.
  - `breadcrumbs` places the trail. `bar` (the default) is a full-width bar under the header: the whole trail, the current page included as its last crumb (marked `aria-current`), so a module's top page shows its trail too (the workspace's home icon, then the module: Home › Bookings). `<Page>` then draws no trail line, and a page with no trail gets no bar. `page` (opt-in) is `<Page>`'s small line above the heading, ancestors only. Either way the trail is drawn once: a `<Page>` nested in another (a tabbed frame round each tab) draws none of its own.
  - `subNav`: `tabs` (the default) lets `<Page>` draw the module's pages as tabs; `strip` keeps the SubNav strip under the header.
  - **Breakout pages.** A page that leaves its parent's shared layout (a full-screen bench, an editor) shows "Back to <parent>" in place of the trail, in either mode. Declare it with the route default `->defaults('kit.breakout', true)` or the ViewModel's `breaksOut()`. The strip is derived from the trail's parent crumb, so it can't drift from the trail. A page inside its parent's layout (a tab, a settings section) is not a breakout and keeps its trail. Account pages (`/me/*`) and a page's own `navigationHeader()` show a strip the same way. A breakout with no parent crumb, or inside a settings section, throws in local and testing; in production it draws no strip and keeps the trail.
  - With no pages to list (no `nav` or `drawer-nav` slot and an empty `navigation`), there is no drawer. The account menu and `actions` stay in the bar at every width.

## Theming

Your app's own `resources/css/app.css` themes the kit. The rule has three parts:

1. **You may remap the surface tokens**, in `:root` and in `.dark`.
2. **Set each surface with its foreground.** Whoever sets a surface sets its pair too, so text on it stays readable.
3. **The kit owns its `--kit-*` component tokens** and the contrast pairs it ships. Don't override them, except on the surfaces named below.

| Surface | Its foreground |
| --- | --- |
| `--background` | `--foreground` |
| `--card` | `--card-foreground` |
| `--popover` | `--popover-foreground` |
| `--muted` | `--muted-foreground` |
| `--sidebar` | `--sidebar-foreground` |
| `--shell-ground` (the shell's page ground; the theme's background when unset) | `--foreground` |

The kit's headings (the Page heading, and GuestShell's and the public Markdown page's titles) use `--font-display`, the serif stack by default. A sans app sets `--font-display: var(--font-sans)` in its `@theme` or `:root`.

You may set `--kit-*` only on `[data-shell-bar]`, `[data-shell-subbar]` and `[data-navigation-header]`.

```css
:root {
    --background: oklch(0.99 0.004 85);
    --foreground: oklch(0.21 0.02 60);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.21 0.02 60);
    --shell-ground: oklch(0.985 0.006 85); /* a lighter page ground than the background */
}

.dark {
    --background: oklch(0.17 0.01 60);
    --foreground: oklch(0.96 0.005 85);
    --card: oklch(0.21 0.01 60);
    --card-foreground: oklch(0.96 0.005 85);
}
```

**Checking your theme.** No kit command checks the theme since 0.18. Read your Vite CSS entries and the files they `@import` for:
- a surface set without its foreground;
- a pair below 4.5:1 contrast;
- a `--kit-*` override outside the named surfaces.

None of these breaks a build; each makes the kit's components harder to read.

## Tinted page grounds

The page ground is white by default. A theme whose ground is tinted (a warm paper, a pale green) declares it with **one token**, in its own `resources/css/app.css`:

```css
:root {
    --kit-ground: tinted;
    --background: #f3f5e7; /* the ground (or --shell-ground for the shell alone) */
    --card: #ffffff;       /* the surface the kit draws on it */
}
```

It is a CSS token rather than a config value because it belongs with the colours that make the ground tinted: one file decides both, a brand preset or a dark theme can carry its own, and a linter can read it from `resources/css`. The kit reads it from the root's computed style when a component is set up (the kit renders in the browser only; there is no server rendering to disagree with). Declare it in the stylesheet the app loads before it mounts.

**What draws a surface.** On a tinted ground these draw their own card, `bg-card text-card-foreground border rounded-xl` (a dashboard widget's look):
- **DataTable**: one card around its tabs, toolbar, filters, rows and pages; the rows inside are flat.
- **EmptyState**.
- **Workbench**: one card around its panes.
- **PageSection**: its content, below the section header. `surface` is `card` by default there; `surface="none"` leaves it on the ground.
- The form layouts (**FormCard** with its FormActions, and the form **Section**) are cards on any ground already.

**Never a card in a card.** Surface, Card, FormCard, Section, a dashboard widget, Dialog and Modal provide a card context (`provideCard()`, from `@kit/ui`; `useInCard()` reads it), and so does each component that drew its ground card. A component inside one draws no card of its own, and a DataTable inside one keeps its rows flat. Your own card-like wrapper should call `provideCard()`, or be a kit Card.

**What stays on the ground.** Page and PageGrid (and its cells) are transparent, and the page chrome stays on the ground: the page header and its actions, breadcrumbs, SubNav, PageTabs and section headings.

**On a white ground nothing changes**: no card is added and no class differs.

**Minimums.** `composer test:token-contrast` checks every shipped theme and a sample tinted theme (`tests/fixtures/tinted-ground-theme.css`, ground `#f3f5e7`), light and dark. The ground is `--shell-ground`, else `--background`; the surface is `--card`.

| Pair | Minimum |
| --- | --- |
| the surface (`--card`) against the ground | 1.1:1, and the card has a `--border` edge |
| `--border` against `--card` | 1.5:1 |
| a control's edge (`--input`) against its surface (`--card`) | 3:1 |
| `--foreground` on the ground, `--card-foreground` on the surface, and `--muted-foreground` on both | 4.5:1 |

A brand can set the edge with the others it prints: `kit.brand.input` (`input` in `config/brand.php`) prints `--input` next to `--border`, and `input_dark` its dark twin, so an app on a tinted ground need not print it itself. Unset prints nothing. `Brand::contrastProblems()` reads a configured edge against the brand's card, else its page, at 3:1, light and dark.

The first three apply to a tinted ground; the shipped themes are white grounds, where only the text pairs do. White against `#f3f5e7` is 1.10:1, so a ground that pale needs a white card. The browser check `?tinted` renders a DataTable, a FormCard, an EmptyState and PageSections on the sample ground at 1280px (light and dark), checks each card's edge and background and that none is nested, runs axe, and checks the white ground draws no card.

## Auth-only session-ui and workspace-ui

`session-ui` is the sign-in, and needs only core and shell: `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` (Fortify's `password.confirm`), `Auth/TwoFactor` (the challenge), `Auth/VerifyEmail` and `Auth/Register`, with Fortify's routes behind them, plus the person's welcome steps (`Welcome/Timezone`, `Welcome/Photo`), the avatar routes, switch account, the passkey offer, the per-person Workbench layouts and its two tables (security events, user state). It installs no workspace, access, Concierge, Pennant or multitenancy, so a single-owner site signs in with core, shell and session-ui alone. Registration is closed by default (accounts come from invitations, or from the host): an app that turns on Fortify's `registration` feature, and opens Concierge's registration mode (`concierge.registration.mode`) where Concierge is installed, gets `Auth/Register` at `/register`, posting to Fortify, which creates the account with the app's `CreatesNewUsers` action, and a "Create an account" link on sign in (Concierge's open mode only). An app's own sign-up (an invited account, say) renders the same page from its own route with a `RegisterViewModel` of its own: its `action`, `heading`, `lead` and `submitLabel`, an address shown as text in place of the email input (`email`, `emailHint`), values posted that are not inputs (`submit`) and its own 429 copy (`throttleMessage`); an error on a key with no input is one alert at the top of the card. `workspace-ui` requires session-ui (and workspaces, access and shell) and holds the workspace pages: the kit's home (`kit.home`) and the dashboard, search, the settings and account landing pages (`Workspace/Sections`), Settings › People, `Settings/Layout.vue`, the account pages under `/me` (`Workspace/Account`), the placeholders, `Workspace/Error`, `Workspace/Index` and `Provision/NoWorkspace`, with the dashboard widgets, the people search provider, the error pages and the workspace opener; and, since 0.6.0, the invitation link (`Auth/Invitation/Show`, its acceptance, the branded invitation email), the owner's workspace-timezone welcome step (`Welcome/WorkspaceTimezone`) and the first-run demo's sign-in. notes requires workspace-ui (its dashboard widget and trail). workspace-ui also has the owner's Transfer ownership and Delete workspace actions and the person's Delete account. Each deletion dispatches lifecycle events, after the commit, so a host can purge its own data in a queued listener ([docs/lifecycle-events.md](docs/lifecycle-events.md)).

- **Auth-only host.** List session-ui in `kit.features` (it brings shell; list core too, add workspaces and access yourself if you want them, and not workspace-ui). The kit serves no page at `kit.home`: that is the app's own, and after sign-in Fortify sends the person there (the kit's `fortify.home` default is `kit.home`; a published `config/fortify.php` decides for itself). Serve your signed-in landing at `kit.home` (`/` unless you set `KIT_HOME`). The last welcome step lands there too, and the welcome pages' trails start at themselves. Your own error page renders errors (the kit's is workspace-ui's). There is no invitation page: invitations are workspace-ui's. Add workspace-ui to the list later for them.
- **Same routes, same names.** workspace-ui's routes are the ones session-ui had, with the same names, loaded right after session-ui's and governed by `kit.routes.session-ui` (prefix, middleware, name, enabled), so nothing a host configured moves. Its classes keep their `Tey\Kit\Features\SessionUi\...` names. A selection with workspace-ui generates exactly the files session-ui generated before the split, at the same bytes; only their owner changes.
- **Hosts from before the split.** Since 0.18 nothing carries workspace-ui for an app: a `kit.features` list that names session-ui and not workspace-ui gets no workspace pages, so name `workspace-ui` after `session-ui` to keep them. The 22 pages session-ui generated before the split are the app's own and stay as they are.
- **The 0.6.0 move.** The invitation page and the workspace-timezone step moved from session-ui to workspace-ui, with their routes and hooks. An app with session-ui alone keeps the two files on disk, unchanged, as its own; their routes come only with workspace-ui, which is needed only for invitations.

## Scrolling lists

`ScrollList` (the `@kit/ui` component) renders a `Tey\Kit\Features\Core\Lists\ScrollListViewModel`: a cursor-paged list that loads more as the viewer scrolls, with an always-present "Load more" button. A list declares its `key()`, `title()`, an already ordered and tenant-scoped `query()`, a `row()` mapping and its first-use `empty()` state; the page passes `$list->items()` as the prop named by the key. The continuation is encrypted and binds the viewer, workspace, list, selection and mode, so a replayed or tampered one is a 400 and never a first page. This is the cursor-only, fixed-selection, count-free subset: sorts, segments, totals, polling, page and simple paging modes and `ScrollWidgetViewModel` from the spec are **not** included. Cursor order is deterministic but not a snapshot: edited or moved records can move between windows.

## Use your own components

Kit pages and the shell import every UI primitive (the form set, buttons, cards, menus) only through one alias, `@kit/ui`, as in `import { Field, Input, Button } from '@kit/ui'`. By default the alias resolves to the kit's own set, which loads from the package at `vendor/tey/kit/resources/js/components/Kit/` (`index.ts`, `contract.ts` and `Form/*`; since 0.18 it is no longer copied into the app, see [docs/kit-components.md](docs/kit-components.md)), built on the shadcn-vue primitives in `@/components/ui` (button, input, label, checkbox, dropdown-menu, sheet, from Laravel's MIT Vue starter kit) and Reka UI. Plain Tailwind otherwise.

Pages follow the ViewModel standard: a page's props are one typed ViewModel (`Tey\Kit\Features\Core\ViewModels\PageViewModel`; every public method with no required parameters is a prop, with a declared return type and a docblock shape for arrays), and the generated TypeScript is read from those methods, so `defineProps<EditClientViewModel>()` restates nothing. `AdHocViewModel` is for a small page (three props at most, no action or permission prop, one render site; the guard fails one that outgrows that). Data classes are values and forms, never the page. There are two producers and two keys: `shell` (the product and viewer, the workspace switcher, the primary navigation and a small shell-only `can` map, shared by the middleware) and the page's own `layout()` (`layout.navigation` for the section navigation, `title`, `documentTitle` and `breadcrumbs`, all decided on the server). Pages declare no layout: the nearest folder `Layout.vue` (`pages/Workspace/Settings`, `pages/Workspace/Account`) renders the section navigation and stays mounted while you move between its sections. Permission checks are memoized per request. **Literal types:** Spatie's transformer writes a docblock literal union (`@return 'queued'|'running'`, `@param 1|2 $level`) as `unknown`. Register the kit's transformers in your `typescript-transformer` config instead of Spatie's: `Tey\Kit\TypeScript\ViewModelTransformer` and `Tey\Kit\TypeScript\AttributedDataTransformer` (in place of `AttributedClassTransformer`), and those literals come out as TypeScript literal unions. Until then, `#[LiteralTypeScriptType("'queued' | 'running'")]` on the property works with any transformer.

**Extending General settings.** To add fields to the workspace's General settings page:
1. Subclass `Tey\Kit\Features\Settings\ViewModels\EditSettingsViewModel` and add prop methods.
2. Register the subclass in a service provider with `EditSettingsViewModel::extendWith(AppSettingsViewModel::class)`.
3. Type the page `defineProps<AppSettingsViewModel>()`.

The kit builds your class with its own constructor arguments, so read anything more from the container inside your prop methods. The generated type is the kit's props plus yours. Registering a class that isn't a subclass fails.

**Page purpose.** Every page states who opens it and their main job, in one line on its ViewModel's class docblock: `@purpose <who>: <main job>`, for example `@purpose Staff: watch unsigned agreements; send one occasionally.` Write it first, before choosing components; the page follows from that job. It's a docblock tag, so there are no props, no TypeScript and no runtime cost. The kit's own page ViewModels carry one, and tey/standards' `ui.page-purpose` rule warns on a page without it.

**Nested ViewModels.** A child component gets its own ViewModel: extend `Tey\Kit\Features\Core\ViewModels\ComponentViewModel`, which has no page concerns.
- **The parent** returns the child from a prop method: `public function summary(): SummaryViewModel`.
- **The page** passes it on: `<Summary v-bind="vm.summary" />`.
- **The component** declares `defineProps<SummaryViewModel>()`.
- **A list** is `/** @return list<RowViewModel> */`, passed on with `v-for` and `v-bind`. A nullable child (`?SummaryViewModel`) works the same way.

The generated page type references the child types, and the parent's payload nests each child's props.

The brand lives in the app's own `config/brand.php`, a committed file with the same keys and nesting as `kit.brand` (publish the commented stub with `php artisan vendor:publish --tag=kit-brand`, or write it from leftover env values with `php artisan kit:brand:migrate`). It is the only source: each key is the value in `config/brand.php` when the key is present (an explicit `null` included), else the kit's default. Since 0.17 the kit reads no `KIT_BRAND_*` env value. One still set is ignored and logged once at boot. The kit writes the result back to `config('kit.brand')`, which every reader keeps using, and `config:cache` caches it.

Colours are a `#rgb` or `#rrggbb` (a `#rgb` is widened). `null` means "use the theme" (or the kit's default): for the accent, the theme's `--primary`, as an empty accent did before. An empty string is invalid: in local and testing the boot throws, naming the key; anywhere else it logs a warning and the key takes the kit's default, never an empty value. `kit:brand:migrate` writes `config/brand.php` from the `KIT_BRAND_*` values still set (run it before `config:cache`); a key in .env that loaded empty is written as `null` with a comment, never a guessed value, so set the real colour there. It refuses to overwrite an existing `config/brand.php` without `--force`, never edits .env, and prints the .env lines to delete. See [docs/upgrading-brand-config.md](docs/upgrading-brand-config.md).

The brand is `config('kit.brand')`: the product's name and logomark, the accent and its companions, the sender addresses and the footer lines. The web and mail footers omit any part that is empty (no dangling separator, no "Built by" with nothing after it). `'private' => true` in `config/brand.php` is for a private app with no public legal entity: the legal name, postal address, Terms and Privacy are left out of both footers, and `Brand::placeholders()` (what `kit:brand` reports as still to fill in) no longer asks for them. Beyond the accent, the brand can set surfaces (`background`, `card`, `border`, `muted`, `input`, each with a `_dark` twin), link colours (`--brand-link`), the button, header and marker roles apart from `style`, a rule under the header, a dark logomark and a lockup; every one is optional and unset prints nothing (see the stub, `resources/stubs/brand.php`). `presets` in `config/brand.php` and the local-only `?brand=` switch compare accent variants side by side.

The lockup (`lockup`, the mark with the wordmark or the product's name) shows where `lockup_scope` says: `guest` (the default: guest and focus pages; mail and the social image use their own images) or `everywhere` (the signed-in bar too). In the bar it is capped at 16rem (45vw on a narrow screen) and its name truncates, so a long workspace name beside it always has room; the bar keeps `[data-shell-product-name]` for assistive tech.

In the signed-in bar the logomark is painted to the bar's mark colour with the theme's brightness and invert filters (`--shell-mark-brightness`, `--shell-mark-invert`). A mark drawn for the bar, a coloured one say, goes in `logomark_bar` (`kit.brand.logomark_bar`, a path in `public/`): the bar then shows that image unfiltered, in light and dark mode, and guest pages keep `logomark` and `logomark_dark`. Unset, the bar is as before. `kit:brand` does not read it.

A lockup reads best with a tightly cropped `logomark`, which may be wide. `kit:brand` draws the favicons, the touch icon and the mail mark from `icon` (`kit.brand.icon`, an SVG path in `public/` or an absolute path) when it is set, and from `logomark` when it is not; `kit:brand --check` compares against the same source. The social image keeps `logomark`, beside the product's name. Whichever source is used, a mark that isn't square is padded to a centred square before it is rasterised, so no asset is stretched or squashed; a square mark renders exactly as before.

`php artisan kit:brand` renders the favicon, touch icon and mail mark from the icon SVG (`kit.brand.icon`, else the logomark) and the social image from the logomark, with Imagick (SVG support) or `rsvg-convert`. ImageMagick's own SVG renderer, without the librsvg delegate, draws no strokes, so a stroked logomark or icon (a `stroke` attribute, or `stroke:` in a `style`) would come out as a dot: kit:brand renders it with `rsvg-convert` when that is installed, and otherwise stops and says to outline the strokes (Inkscape: Path › Stroke to Path; Illustrator: Object › Path › Outline Stroke; Figma: Outline stroke) or install librsvg. A mark of filled shapes renders anywhere.

The accent for these assets is `kit.brand.accent`. Set it to `null` in `config/brand.php` ("use the theme") and kit:brand draws them in the accent the theme resolves to: the light-mode `--primary` (or `--color-primary`) in `:root` of `resources/css/app.css`, the Vite CSS entries and their local imports, as hex, `rgb()` or `oklch()`. The app's own value wins over a `kit.css` copy's default. When no stylesheet sets one it can read (only a `var()`, say), kit:brand stops, says to set the accent, and writes nothing, so no asset is ever drawn in an empty colour. A set accent, and the default zinc, work as before.

Some apps draw an output by hand, a favicon.ico tuned pixel by pixel say. List those in `kit.brand.own` (in `config/kit.php`; names in `public/`, such as `['favicon.ico', 'og-image.png']`, a leading `/` or `public/` is ignored): kit:brand never overwrites them, prints `skipped public/<file> (the app's own, kit.brand.own)` for each, and `--check` skips them instead of calling them out of date. The default is an empty list, so kit:brand writes every file, as before.

By default (`'icon_colours' => 'mono'`) kit:brand draws the mark in one colour: white on an accent tile for the favicons and the touch icon, white on the social image, and the accent for the mail mark. A mark with several colours comes out as one flat shape that way, so set `'icon_colours' => 'full'` in `config/brand.php`. The mark then keeps its own fills and strokes in all four images (only `currentColor` still takes the one colour). Two optional keys shape the favicon and touch-icon tile in either mode. `icon_padding` is the space on each side as a fraction of the tile, from 0 (the mark edge to edge) up to 0.5, and unset is the usual 14/64. `icon_background` is a `#rgb` or `#rrggbb` for the tile, unset is the accent, and `none` leaves the favicons transparent. The touch icon keeps the accent then, because iOS shows a transparent touch icon on black. A bad value for any of the three is refused as it resolves, as a colour is. Left unset, every file is the same, byte for byte.

The account pages under `/me` (Profile, Password, Security) use Fortify's own features and actions, wired by the kit: the name and email (a changed address stays unverified until its link is followed, and the page says so), the password (other sessions are signed out afterwards), and two-factor authentication (the password is asked for again, a code from the authenticator turns it on, recovery codes are shown once, and turning it off or regenerating the codes confirms first). The host's user model needs Fortify's `TwoFactorAuthenticatable` trait and `MustVerifyEmail`, and the `users` table Fortify's two-factor columns (the starter has both). Signed-in devices can be listed and ended only with `SESSION_DRIVER=database`; the page says so otherwise.

**Sessions for MCP:** set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment, not the platform default. Passport keeps the consent request in the session, which outgrows a cookie session's 4 KB, so consent would fail. On `SESSION_DRIVER=cookie` the consent routes refuse with an error page and log why. The tey/standards preflight `install.sessions` also checks it at deploy. The security log is the kit's `kit_security_events` table, written from the sign-in, failed sign-in, password and two-factor events; it belongs to the user, is never shown in a workspace and is kept.

Forms follow one shape: Inertia's `<Form>` (uncontrolled, so every control submits a native named value) with the kit's `Field name`, ending in `FormCard` or `FormActions`: the bar with the form's status, Cancel and the submit button, sticky, with a leave-page warning while the form is dirty; a destructive action goes through `ConfirmDialog`, and Delete lives in its own danger-zone section, never in the bar. The peer dependency is `@inertiajs/vue3` ^3 (`useFormContext` needs 2.3.9 or later). A form saved by the app's own handler (a fetch, `useHttp`, a socket) with no visit uses [`useSubmitForm`](docs/submit-form.md) as the `:form` of `FormCard`, `FormActions` or `Workbench`.

The kit's Reka components need `reka-ui` 2.11.0 or later (`InputSelect` does not typecheck on earlier releases). Core lists it under `requires.npm`; the host's `package.json` must provide it, because the kit installs no npm packages.

Add the aliases in `vite.config.ts` and `tsconfig.json`, and the `@source` line in `resources/css/app.css` (the full recipe is [docs/kit-components.md](docs/kit-components.md)):

```ts
// vite.config.ts
const kitComponents = path.resolve(__dirname, 'vendor/tey/kit/resources/js/components/Kit');
resolve: { alias: { '@/components/Kit': kitComponents, '@kit/ui': `${kitComponents}/index.ts` } }
```

```json
// tsconfig.json, compilerOptions
"paths": { "@/components/Kit": ["./vendor/tey/kit/resources/js/components/Kit/index.ts"], "@/components/Kit/*": ["./vendor/tey/kit/resources/js/components/Kit/*"], "@kit/ui": ["./vendor/tey/kit/resources/js/components/Kit/index.ts"] }
```

```css
/* resources/css/app.css: Tailwind skips the git-ignored vendor/ unless it is named */
@source '../../vendor/tey/kit/resources/js/components/Kit';
```

In dev, add `kitImports()` from `vendor/tey/kit/resources/js/components/Kit/kitImports.js` to the Vite plugins, after `vue()`. It rewrites each `@kit/ui` import into imports of the files it names, so a page loads only the kit components it uses. Production builds are unchanged. See [docs/kit-imports.md](docs/kit-imports.md).

To use your own components, point `@kit/ui` at a module of yours that exports the same names, with the props in `vendor/tey/kit/resources/js/components/Kit/contract.ts`. The kit's pages import these from it (mcp's Authorize page imports `InputSelect`, for instance), so your module must export each of them: <!-- kit-ui-names -->`ActivityStream`, `Alert`, `AppearanceToggle`, `Avatar`, `Badge`, `Breadcrumbs`, `browserTimezone`, `BulkDrop`, `Button`, `checkedAtLabel`, `Checklist`, `confirmPasskeyPassword`, `CopyButton`, `DataTable`, `DateTime`, `Dialog`, `Dropdown`, `DropdownConfirm`, `DropdownItem`, `DropdownMenu`, `DropdownMenuContent`, `DropdownMenuItem`, `DropdownMenuLabel`, `DropdownMenuSeparator`, `DropdownMenuTrigger`, `EmptyState`, `Field`, `FileInput`, `FormActions`, `FormCard`, `FormStatus`, `fromLine`, `fromPreview`, `fullRecordName`, `Input`, `InputCheckbox`, `InputSelect`, `Modal`, `opensSearch`, `Page`, `PageEnd`, `PageGrid`, `PageSection`, `passkeyName`, `PasskeyOffer`, `passkeySignInSupported`, `passkeysSupported`, `PasswordInput`, `Placeholder`, `provideCard`, `RadioCardGroup`, `recordKind`, `registerPasskey`, `ScriptForm`, `ScrollList`, `SearchBox`, `Section`, `SectionIcon`, `Select`, `SettingsFrame`, `Sheet`, `SheetContent`, `SheetDescription`, `SheetTitle`, `SheetTrigger`, `signInWithPasskey`, `Stack`, `Surface`, `Textarea`, `TimezoneSelect`, `Toaster`, `useClock`, `useTimezone`<!-- /kit-ui-names -->. Kit pages then render your components, with no forking of the pages. Re-export what you do not replace: `export * from '@/components/Kit'`, then your own named exports.

| Component | Props | Notes |
| --- | --- | --- |
| `Field` | `id`, `label`, `description?`, `error?` | The control goes in the default slot and takes the same `id`; `error` is an alert |
| `Input` | `invalid?` | `v-model`; other attributes pass through |
| `Textarea` | `invalid?` | `v-model` |
| `Select` | `options`, `id?`, `placeholder?`, `disabled?`, `invalid?` | `v-model`; `options` are `{ value, label }` |
| `TimezoneSelect` | `id?`, `disabled?`, `invalid?` | `v-model`: an IANA zone name |
| `Checkbox` | `id`, `disabled?` | `v-model`: boolean; the label is the default slot |
| `Button` | `variant?` (`primary`, `secondary`, `destructive`), `type?` | A submit button by default |
| `FormActions` | none | The row that ends a form |
| `Section` | `title?`, `description?` | A card for a group of fields |

The kit's own checks refuse a generated page or shell file that imports UI from anywhere but `@kit/ui`.

## Page header actions

`<Page :actions="...">` takes the page's server action descriptors (`Tey\Kit\Features\Core\Data\Action`) and splits them with one rule, `headerSplit` (`Kit/Action/headerSplit.ts`): one filled primary, up to two outline secondaries, and a "More" menu with the rest, in descriptor order everywhere. The menu is the kit's action menu, so dialog and document intents, confirmations (typed ones too), disabled actions with their reason and the hiding of edit actions in a locked form all behave as in ActionBar. A non-navigating action is emitted as `action` when the page listens, else the header runs it as ActionBar does.

- **Priority.** An action may declare `priority`: `primary`, `secondary` or `overflow` (the trailing constructor argument, `null` by default). A declared priority always wins. If more than one action is `primary`, the first stays primary and the later ones show as secondaries (a console warning in development). Secondaries beyond two go into More, in order.
- **Defaults.** With no priority declared, three actions or fewer render exactly as today: through ActionBar, with the same markup as passing those descriptors to ActionBar in the `actions` slot (its placement rule and container fit decide). With more than three, the first non-destructive action is primary, the next two non-destructive ones are secondaries, and everything else goes into More. Navigation GETs count like any other action. A destructive action is never made primary or secondary by default.
- **Status.** The `#status` slot, or the `status` prop (`{ state: 'saved' | 'saving' | 'idle' | 'error', label }`, a small icon and the label), sits before the actions in a muted `text-sm` live region the kit owns (`role="status"`, `aria-live="polite"`), never a button.
- **Phone.** Below `sm` (a media query, not the container's width) only the primary stays visible; the secondaries move to the top of More. Above `sm`, a header that is too narrow wraps, and the three-or-fewer ActionBar path still collapses as ActionBar does.
- **Mirror.** `FloatingToolbar` takes the same `actions` and `status` and splits them the same way, so the bottom pill repeats the header. Its non-navigating actions go to `onPageAction` (`@page-action`); `onAction` stays the handler for its client `items`. The Workbench's actions row splits its `actions` the same way (three or fewer keep its ActionBar, the first filled), beside its save state.
- **Escape hatch.** Whatever the `#actions` slot holds renders as is, after the split actions, with no rule applied.

## The kit's TypeScript types

The kit's pages are typed with its data and view-model types, in global `Tey.Kit.Features.*` namespaces. The kit ships them, generated from its classes, in `vendor/tey/kit/types/kit.d.ts`. That's the supported source: add it to `tsconfig.json`'s `include`, and it stays in step with the kit you have installed:

```json
"include": ["resources/js/**/*", "vendor/tey/kit/types/kit.d.ts"]
```

A page of yours that types its props with them (`defineProps<Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel>()`) also needs the file in `@vitejs/plugin-vue`'s `script.globalTypeFiles`, since Vue's compiler resolves those props at build time:

```ts
vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })
```

The `shell` feature's `vite_global_types` host step names this; since 0.18 no kit command checks it, so check the Vite config yourself. An app that writes the `Tey.Kit` namespaces itself (its own transformer's generated `.d.ts`) and lists that file in `globalTypeFiles` needs neither this entry nor the tsconfig include, as long as the file declares `namespace Tey` with `namespace Kit` directly inside (or `namespace Tey.Kit`). Don't list it beside the kit's `kit.d.ts`: each type would be declared twice.

The shell feature's host steps list this, along with the `@kit/shell` and `@kit/ui` aliases. Transforming the kit's `src` with your own transformer isn't needed, and a transformer that writes one flat module can't produce these namespaces. If yours already writes them (the global-namespace writer over `vendor/tey/kit/src`), leave the include out, since both would declare each type twice. The dashboard's view model is typed `WorkspaceDashboardViewModel`, so it never collides with an app's own `DashboardViewModel`.

A host that copies the file instead (`resources/js/types/kit.d.ts`) copies it again after each kit upgrade that changes it. The kit's CI keeps `types/kit.d.ts` in step with its classes (`composer test:kit-types`; `php bin/kit-types.php` rewrites it).

## Computed values and their working

`ComputedValue` shows a figure the server calculated and lets the reader open how it was reached. The server sends a `Tey\Kit\Explanation\ExplanationDocument` (Laravel Data), and each root `ExplanationNode` carries its display string, exact minor units, inputs with their sources, the rule applied, rounding and allocation. The kit validates the document's structure and refuses floats, dangling operands, unsafe links and oversized trees. Arithmetic, reconciliation and authorisation stay with the app. The browser only displays the strings it is given.

```vue
<ComputedValueScope scope-key="invoice" remember>
    <ComputedValueToggle />
    <ComputedValue :node="explanation.roots[0]" label="Invoice total" placement="overlay" />
</ComputedValueScope>
```

`placement` is `inline` (the default), `table-row` or `overlay` (a popover, or a bottom sheet on a phone). `ComputedValueRoot` is the headless version, for your own markup. The `Explanation*` types come from `@kit/ui`. They are generated from the PHP classes by `php bin/generate-explanation-ts.php`, and `--check` fails when they are stale. Never edit the generated file by hand. A page view model that carries a `list<ExplanationNode>` or an `ExplanationDocument` types it as the kit's own type when the host registers `Tey\Kit\TypeScript\ExplanationTypesExtension` in its typescript-transformer config (`$config->extension(new ExplanationTypesExtension)`); without it `spatie/laravel-typescript-transformer` emits `undefined` for those props and the page has to cast. The generated types import from `@kit/ui`, so the host's tsconfig must resolve it.

## Releasing

Before tagging a release, run `php bin/catalog-history.php` and commit `resources/features/history.json`. It records each tagged release's file hashes per feature, plus a `current` entry for the catalog being tagged. `php bin/catalog-history.php --check` fails when `current` no longer matches the live catalog, so regenerate it in the same PR as any change to a feature's stubs or manifest. A change to a feature's stubs or manifest also changes its pin: `php bin/catalog-pins.php` prints every feature's current identity next to its pin and marks it `ok` or `changed`. Copy a changed one into `src/Catalog.php` and `tests/StarterCatalogCheck.php`, then regenerate the history. With `--check` (a CI step) it exits 1 while any pin is stale.
