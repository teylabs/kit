> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

# tey/kit

Feature kit for tenant-aware Laravel apps, built for the Tey Labs fleet. An app adopts features from a catalog with `kit:add`, one Mod lineage per feature, updates each with `kit:update --lineage=<feature>`, and lists what the catalog offers with `kit:list`. `kit:status` is planned and does not exist yet. The package carries an opinionated versioned application contract and a public Mod scaffold pack. A/B and C/D are local synthetic release fixtures, not published versions. Runtime tenancy/membership/settings stay in maintained packages and host bindings.

## Quick start

```sh
composer require tey/kit
php artisan kit:install
```

`kit:install` writes the app-owned host binding `kit:add` needs: `app/Kit/KitHost.php`, the operation check in `app/Kit/operations.php`, `config/kit-lifecycle.php` (verification policy and app id), `config/kit-executor.json` (the Mod runtime) and `app/Providers/KitHostServiceProvider.php`, and adds that provider to `bootstrap/providers.php`. Those files are yours: no feature owns them, and `kit:add` and `kit:update` never touch them, except `kit:update refresh-executor`, which you run after upgrading tey/mod to record the new runtime in `config/kit-executor.json`. Until you do, every plan stops once with `executor_stale`, naming the recorded and running versions and that command. It refuses to overwrite any existing file, and a second run changes nothing.

Review what it prints, above all the operation check, which fails closed until the host schema exists (`php artisan migrate`). `workspaces` also needs your user model to be a workspace member. `app/Models/User.php` is yours, so kit never writes it: add the lines yourself.

```php
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;

class User extends Authenticatable implements Member
{
    use HasWorkspaces;
}
```

`kit.models.user` (else the `users` auth provider's model) and `concierge.models.user` must name that model. Until it implements `Member`, the preview lists the host step as unmet, with the lines to add, and the plan is not executable. Then preview, apply and migrate:

```sh
php artisan kit:add workspaces
php artisan kit:add workspaces --apply --expected-plan=<the plan the preview printed>
php artisan migrate
```

Without `kit:install` (or your own `LifecycleHost` binding), `kit:add` refuses.

Recipes and guides:

- [Public site](docs/public-site.md): core, shell and session-ui with the PublicShell, registration off, no workspaces, workspace pages or invitations.
- [Upgrading from kit 0.2.x](docs/upgrading-from-0.2.md): claim core, then move kit and mod together.
- [The host's repository](docs/host-repository.md): which `.mod-maintenance` files to commit, and setting up a fresh checkout or CI.

The scaffold id is `tey/kit` and its command is `kit:foundation`. This package was renamed before first publication; there are no aliases. See the changelog.

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

An unknown set name is refused when the component renders, and the error lists the three sets.

## Features

The app is a set of features. Each feature has a manifest at `<catalog>/<id>/feature.json` with its id, owned paths, generated files (with their stubs), requirements (other features and packages), conflicts and host-owned paths. An optional `host_steps` names changes only the host can make. `kit:add` and `kit:update plan` verify each one and print it; they never write it:

- `user_model`: the user model must implement `implements` and use the trait `uses`, checked by reflection. It blocks: while it is unmet the plan is not executable, and `--resume` refuses it too.
- `workspace_model`: `concierge.models.workspace` and `multitenancy.tenant_model` must name one Eloquent model that implements `implements` and spatie's `IsTenant` and uses the trait `uses`, checked by reflection. It blocks as `user_model` does. See [Adopting into an existing workspaces table](#adopting-into-an-existing-workspaces-table).
- `env` (`[{key, why, or_file?}]`): the key must be documented in the host's `.env.example`. The kit never reads `.env`. Met means documented, not configured, so set the key in each environment. With `or_file`, the step is also met when that file exists, for a secret kept as a file (such as `storage/oauth-private.key`). Only its existence is checked; the file is never read, and a linked one doesn't count.
- `provider` (`[{class, why}]`): the service provider must be registered.
- `schedule` (`[{command, why}]`): a scheduled event must run the command. Any form counts, wherever the schedule lives (routes/console.php, bootstrap/app.php's `withSchedule`, a Console Kernel): `Schedule::command('model:prune --model=X')`, `Schedule::command('model:prune', ['--model' => [X::class]])`, a string option value, or several models.
- `config` (`[{key, not, why}]`): the config value at `key` must not be `not`. It is checked where the plan runs, so it is met for that environment only; each deploy environment may differ.
- `theme` (`[{file, why}]`): the host's stylesheet (core declares `resources/css/app.css`) imports the kit's `kit.css`, which carries the colour tokens' defaults, or defines every semantic token the kit's components use itself. The token can be a plain variable (`--primary:`) or a theme mapping (`--color-primary:`). Unmet, it names the missing tokens and the import. It reads that file, every CSS entry your `vite.config.{ts,js,mjs}` names (so `kit.css` imported from a second Vite entry counts), and the local files each one `@import`s, transitively (inside the app, never through a link). It never runs the Vite config.
- `mention` (`[{file, contains, why}]`): the host file must mention `contains` (a cheap text check, never blocking). A step on `vite.config.ts` reads whichever Vite config the app has (`.ts`, `.js` or `.mjs`). A step on a script under `resources/js` (`app.ts`) also reads every script entry the Vite config names (`next.ts`), and any of them mentioning it meets it.

The others never block. An unmet one is listed under `steps`, added to `host_steps`, and raises `attention`. So does a `requires.npm` entry that the host's `package.json` doesn't declare (`missing`), declares with a range that allows older versions (`outdated`), or declares in a form the kit can't read (`unverifiable`: `workspace:`, a git URL, a tag). The kit installs nothing. An optional `optional` list (`[{id, title, do}]`) names integrations a host may add; they're printed as `manual` and never checked. The text preview opens with an ATTENTION block that lists every warning. The JSON output adds `steps`, `npm` (constraint, declared range, status, and the installed version from `node_modules` with its own `installed_status`), `optional`, `attention`, `warnings`, and per feature `migrations` (`new`, `changed`, `existing`); every existing key is unchanged. A `kit:add` plan identity binds each step's status and each npm requirement's constraint, declared range and status, so a change to them after review needs a fresh preview. The installed version isn't bound, so an `npm ci` between preview and apply keeps the plan. `kit:update plan` keeps Mod's plan identity: the declarations are bound through the target artifact, and their outcomes are reported only. `<catalog>/blueprint.json` holds what every selection shares: the artifact name, version, groups, migration order and verification policy.

**Colour tokens.** The kit's components are coloured with shadcn-vue's semantic tokens (`bg-primary`, `text-muted-foreground`, `border-input`, `ring-ring` and the rest). `kit.css` defines them all at the lowest precedence, so a host with no theme still renders the kit in colour, and any host theme wins:
- an `@theme default inline` block maps each `--color-*` to its plain variable; a host's own `@theme` replaces it;
- the starter kit's neutral values sit on `:where(:root)` and `:where(.dark)`, which any `:root` or `.dark` of the host overrides, whatever the import order.

Import it after Tailwind: `@import './kit.css';`.

**Host UI primitives (`requires.host_ui`).** The kit's components import a few UI primitives from the host's `@/components/ui` (shadcn-vue's): core declares `button`, `checkbox`, `dropdown-menu`, `input`, `label`, `sheet` and `spinner/Spinner.vue`. A name is met by `resources/js/components/ui/<name>/index.ts`, and a `<name>/<File>` entry by that file. A missing one is a broken build, so it **blocks**: `kit:add`, `kit:update plan` and `kit:adopt plan` list one `host_ui` step in `unmet` with the exact command, for example `npx shadcn-vue@latest add button checkbox dropdown-menu input label sheet spinner` (only the missing ones), and report each primitive under `host_ui`. An update or adoption checks the primitives the files it keeps import. The kit's library check fails when a stub imports a primitive its feature does not declare. The kit has its own `cn` (`resources/js/components/Kit/utils.ts`, clsx and tailwind-merge), so nothing depends on the host's `@/lib/utils`.

**`@inertiajs/core`.** `@inertiajs/vue3` pins `@inertiajs/core` exactly, so core requires `@inertiajs/vue3` and not `@inertiajs/core`. No kit stub imports `@inertiajs/core` (the library check enforces it): the few Inertia types it needs are its own, in `resources/js/components/Kit/types.ts`. A host `package.json` that also declares `@inertiajs/core` at another version than vue3 pins (an exact match is fine), or a `node_modules` holding two copies of it, raises attention: two copies' types disagree, and PageProps augmentation stops applying. Remove the host's `@inertiajs/core` line (or set it to vue3's exact version).

The package catalog, `Catalog::package()`, is the starter catalog: core, workspaces, access, settings, shell, session-ui, workspace-ui and mcp, plus the opt-in notes ([docs/catalog.md](docs/catalog.md)). The v0.1 features `foundation` (the policy file) and `defaults` (the defaults file, which requires `foundation`) are a regression fixture in `tests/fixtures/v01`, loaded by `tests/fixtures/v01.php`; they are not in the package catalog. The whole app is every feature. Select features with `Blueprint::features(['foundation'], $catalog)`, `Blueprint::all($catalog)`, or `config('kit.scaffold_features')` for the v0.1 scaffold fixture, which builds from the bound host's catalog when it implements `CatalogHost`.

A selection is checked before anything is built. It is refused when a feature is unknown or listed twice, a required feature is missing, two features conflict, a file sits outside its feature's owned paths or inside a host-owned path, two features own the same path, or two features disagree on a package constraint.

Each feature is content-addressed: its manifest bytes and its stub bytes are pinned in `Blueprint`. A selection's source reference covers the composed manifest, every resource byte and every feature's pinned identity. The order you list features in does not change it. The selection is recorded in the manifest and in the artifact metadata (`blueprint_features`).

Mod refuses changes to an artifact's name, groups or bindings, and refuses removals. `kit:add` installs each feature as its own lineage (`tey/kit:<feature>`). The release fixtures and `kit:update --features` keep the older single-lineage path, one growing artifact:

- A first adoption has no baseline. The original is an empty artifact with the same name, groups, bindings and checks as the target, and no metadata, as Mod builds its own. Existing paths are refused as unowned, even when the bytes match.
- Adding a feature is an upgrade from the old selection to a larger one on the same artifact.
- Removing a feature is refused with a clear message, before any other check.

`kit:add` claims existing files only from an explicit, reviewed list. Its preview lists each existing file whose bytes already equal a feature's file as `claimable` and leaves it unowned, so the plan is not executable. Review each one, then list it with `--claim=<path>` (repeat the option, or separate paths with commas) in both the preview and `--apply`. The claim list is part of the plan identity. Only listed paths are claimed, without a write. A listed file with different bytes is a conflict, and an unlisted one is refused as unowned. A claim that names no file of a feature being added is refused.

When an apply stops, `kit:add <features> --resume --intent=<id>` (with the same `--claim` list) finishes the interrupted feature, then adds every remaining feature of the request in requires order. It prints which features are done and which remain, and exits nonzero unless all of them completed. While a lineage has an unfinished update, `kit:add` and `kit:update` refuse with `pending:<lineage>`, then print that lineage's intent and the exact resume command. `kit:update status` lists each unfinished journal as `Pending` (resume it) or `Stale` (an update that completed, whose target identity is already installed), and exits nonzero while any remains; `kit:update status --clear-stale` removes the stale ones only and prints each one. A completed `kit:update apply --lineage` clears its lineage's stale journals, and `kit:update resume` on a stale journal clears it.

An app that installed features before lineages existed adopts them in place: `kit:adopt <feature> plan`, then `kit:adopt <feature> apply --intent=<id> --exclusive-checkout --expected-plan=<plan>`, one feature at a time in requires order. Plain `kit:update` prints these commands on such an app, and each adoption plan prints its exact apply command (`apply` in its JSON). A new migration creating a table the host already has makes the plan not executable, as in `kit:add`. Adoption overwrites nothing:
- A file identical to a known release (`resources/features/history.json`, or the current catalog) is claimed.
- A missing file is created.
- An edited file stays the app's, recorded against the release it came from: `--from=<release>`, else the closest one.

Each lineage's plan is reviewed on its own. A plan whose feature requires one with an update still pending lists it in `depends_on` and gives the order in `order`: plan and apply that one first. There is no single up-front review of every pending lineage yet.

From then on `kit:update --lineage=<feature>` leaves a kept file alone until a release changes it. It is then a conflict, as an edited owned file is, and it is claimed once the app's bytes match. A plan that would replace a file under `database/migrations/` lists it in `changed_migrations`. The apply is refused without `--accept-changed-migrations`, because a migration that already ran never runs again: make the schema change in a new migration.

**Keeping a file for good, or handing it back.** A file the app changed and means to keep (its own `Layout.vue`) shouldn't block every later update.
- `php artisan kit:update keep <path> [--lineage=<feature>]` plans it as **host-kept**, and the printed apply command records it. The lineage stops owning it: an owned file is released to the app untouched, and a file kept since adoption stops being kept. No later update writes it, claims it or conflicts on it, and the lineage's other files still update (a re-pin's new partial is written).
- Each `kit:update plan --lineage=<feature>` lists, under `held`, an `ATTENTION:` line for each kept file the kit changed upstream since the host last saw it, with the stub to diff against, so you can port the change by hand. That includes a change made before the keep: what the host last saw is the release its edit was made against (for a file kept since adoption) or the bytes installed (for an owned file). Each change is reported once; the next apply records it.
- `php artisan kit:update claim <path>` is the reverse: the file's current bytes become the lineage's original, so the next update replaces it with the kit's bytes while it's unchanged since the claim, and plans a conflict once it's edited after.
- Neither writes a file; each changes only the record, after a reviewed plan (`--expected-plan`, `--exclusive-checkout`, `--intent`).

Per-feature artifacts need an additive Mod API and are not part of 0.1. The manifest shape is ready for it.

### Adopting into an existing workspaces table

A host app that already has its own `workspaces` table, its own workspace model and spatie/laravel-permission's tables (with teams) adds the `workspaces` and `access` features without a second workspaces table or second permission tables.

1. **Bind your model.** Publish `config/concierge.php` and `config/multitenancy.php`, and set `models.workspace` and `tenant_model` to the same class. The model extends spatie's `Tenant` (or implements `IsTenant`), implements `Tey\Concierge\Contracts\Workspace` and uses `Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace`, which carries the kit's slug hooks, `memberOf` scope, `conciergeName()`, `conciergeAcceptsMembers()` (from `admitted`) and `required()`. The kit creates workspaces with `name`, `admitted` and `provisioning_invitation_id`, so those must be fillable. The plan refuses a binding where the two keys differ or the model misses the contract, `IsTenant` or the trait (`workspace_model`).

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

2. **Plan.** `kit:add workspaces access` lists each host table it adopts (`adoptions` in the JSON, an `adopt the host's ... table in place` line in the text) instead of refusing it as a collision. Adoptions never block. Any other new migration creating a table the host has still blocks. So do permission tables that exist only in part, or that lack the configured teams column (`permission.column_names.team_foreign_key`, `workspace_id` by default) while `permission.teams` is on.
   **Fresh databases.** When the plan adopts, it records the adoption in the workspaces lineage's installed record and publishes the migrations of every feature it adds that reaches workspaces (workspaces, access, settings, mcp, notes) as `<stamp>_<order>_<name>.php` instead of `0001_01_02_<order>_<name>.php`. The stamp is one second after your latest migration under `database/migrations` (outside `database/migrations/kit`), and no earlier than today (UTC). So on an empty database (CI, a new developer, tests) `migrate` runs your own `workspaces` and permission migrations first, then the kit's, and ends with the same schema as adopting over an existing database. Commit your own migrations before you run `kit:add`. `kit.workspaces.adopt` and `kit.access.adopt` (null by default, which reads that record; `KIT_WORKSPACES_ADOPT` and `KIT_ACCESS_ADOPT`, or your own `config/kit.php`, override it; set them to true before planning when the database you plan against doesn't have your tables yet) tell the workspaces and permission migrations that the tables are yours. They then refuse, rather than create, a table that doesn't exist yet. A host that adopts nothing keeps the catalog's names. `kit:update` never renames an installed migration, and a migration a later release adds takes the install's stamp (or the catalog's name on a non-adopting install).
3. **Migrate.** The workspaces migration adds only the columns the kit needs and the table lacks: `admitted` (default true; every existing row is set to true), `settings_version` (default 1; every existing row is set to 1), `provisioning_invitation_id` (nullable, unique) and `slug` (filled from the name for each row without one). It never drops or alters a host column. The permission migration creates nothing when all five spatie tables exist.
4. **Seed operators before deploy.** With workspaces, tenant admin (`concierge.manage-tenants`) is a row in `operators` on a verified, active account. Concierge's default, the `concierge.tenant_invitations.platform_owners` emails, no longer applies, and `operators` starts empty, so without a seed the platform owner loses tenant admin on deploy. `php artisan kit:operators:seed --from-platform-owners --dry-run` lists what it would seed; without `--dry-run` it adds an operator for each platform-owner email whose user (matched on the trimmed, lower-cased `email` column) is verified and active. It is idempotent, and reports already-operators, skipped users (unverified or inactive) and emails with no user. While platform owners are configured and `operators` is empty, `kit:add`/`kit:adopt` plans show an attention step (never blocking) with the emails that would match, `kit:status` warns, and boot logs a warning in `local` and `testing` only (production boot runs no query for it). That output shows addresses masked (`j***@example.com`) with counts; only an interactive `--dry-run` shows unmatched emails in full, so you can fix them. The seed and the step read `config('concierge.tenant_invitations.platform_owners')`, never the environment directly, so a cached config decides. The step is part of the reviewed plan: seeding between `kit:add` preview and `--apply` changes the plan identity, so re-run the preview and apply with the new one. `composer test:operators-seed` covers it on SQLite.

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

A plan that adopts your `workspaces` table lists this as a host step.

Some things a rollback leaves in place, on purpose:

- **The audit key migrations are forward-only.** `0001_01_02_000204` to `000207` have no-op `down()`s. After a rollback, `audit_events.workspace_id` stays nullable and keeps its `ON DELETE SET NULL` key. Making the column required again would fail on rows whose workspace was deleted, and restoring a key without `SET NULL` would block deleting those workspaces again. Only `000208`'s `workspace_name` column is dropped.
- **The access seed survives.** Rolling back `access` drops `kit_permission_defaults`, the record of which default grants `kit:permissions:sync` applied. The permissions it created (such as `workspace.members.manage`) and their `role_has_permissions` rows for owner and admin stay, because your app may have assigned them since. To remove them by hand, delete the permissions you no longer want from spatie's `permissions` table (their `role_has_permissions` and `model_has_permissions` rows go with them by cascade), then run `php artisan permission:cache-reset`.

`migrate --pretend` on an adopting host, before the kit's migrations run, lists the adoption's statements (the columns added to your `workspaces` table and the `kit_adoptions` record) instead of refusing. Pretend runs no query, so the migrations can't see that your tables exist, and with `kit.workspaces.adopt` or `kit.access.adopt` on they take the adoption path. With the flags off it shows the statements that create the tables.

Not covered: `composer test:adopt-fresh` runs `kit:add` and `kit:update` through `Lineages` (not the artisan commands) on a synthetic catalog copy carrying the real workspaces and access migrations, then compares the fresh-database schema with the production adoption and runs `migrate --pretend` on an adopting host, on SQLite and in CI's PostgreSQL job (its own `kit_fresh_*`, `kit_prod_*` and `kit_plain_*` schemas). `composer test:workspace-adopt` runs on SQLite memory and, in CI's separate PostgreSQL job, against a PostgreSQL service container (`DB_CONNECTION=pgsql` with the `DB_*` variables; it drops and recreates the `public` and `kit_second` schemas, so point it only at a throwaway database). There is no MySQL run and no end-to-end `kit:add` against a real host app.

## Feature library

The starter's features run from the library under `src/Features/<Feature>` (`Tey\Kit\Features\...`): core, workspaces, access, settings, shell, session-ui, workspace-ui, mcp and notes. `Tey\Kit\Features\FeaturesServiceProvider` (auto-discovered) registers the providers of the adopted features, `config('kit.features')` or else the installed record's `blueprint_features`, loads their migrations from `database/migrations/kit/<feature>`, and fills the `Registry` they share: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, the connection guard and error codes. A selection with an unknown feature, a duplicate or a missing requirement is refused.

Routes and config ship in the library: no feature generates a config file. The kit's defaults merge in with nothing published; `php artisan vendor:publish --tag=kit-config` writes `config/kit.php` as the app's own file, which kit updates never touch. `kit.routes.<feature>` takes `enabled`, `prefix`, `middleware` and `name` (a route-name prefix). Other keys: `kit.models.user` (default: the `users` auth provider's model), `kit.capabilities` (capability => on), `kit.invitation_redirect` (a route name taking `workspace`; default: the first navigation link) and `kit.mcp.connection_ability` (default `settings.read`). A delegated (MCP) principal is refused unless mcp is adopted. Controller redirects still use `/workspaces/...` paths, so a route `prefix` does not move them yet (kit #12).

## Listing the catalog

`kit:list` prints each feature of the catalog: its id, the features it requires and the paths it owns. `--json` prints the same as JSON. It is read-only and uses the bound host's catalog when the host implements `CatalogHost`, else the package catalog.

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

To let `kit:update` use a catalog, implement `CatalogHost` on the bound `LifecycleHost`. Its `catalog()` is used to build the `--features` and `--original-features` blueprints. A host without it uses the package catalog. The command does not change. `--target` and `--original` still name release fixtures. An app or package can implement `CatalogHost` for its own catalog and keep its own commands. A host's own command can also build blueprints with `Blueprint::features($ids, $catalog)` and call `Lifecycle::plan`, `apply` and `resume` directly.

A–D stay as release fixtures. They are separate regression evidence, not feature sets: their stub bytes carry their own versions (0.1.0 to 0.4.0). Their manifests use the `groups` key, and their pinned identities changed with it (see the changelog).

The `tey/kit` scaffold uses public released Mod APIs. Preview with `kit:foundation --dry-run --json`; then generate explicitly with `--no-interaction`. A/B generate one policy file. C/D add a separately owned defaults file for the two-file upgrade proof. It does not establish a bootable/authenticated application, persisted ownership, upgrade/recovery, browser or product-MCP acceptance.

Generated paths and host-owned branding/domain/configuration/data are distinct in the blueprint. The source reference content-addresses the manifest plus all resource bytes; baseline resources remain available for comparison. Generic lifecycle machinery lives in Mod. No private Mod APIs, forced overwrites, semantic merging, package publication or automatic production updates.

The initial recipe fixes the member name and ungrouped placement. Alternate command names cannot widen ownership. Only the Laravel layout with all declared paths and classes is supported; incompatible layouts refuse before generation.

The original A/B resource bytes and source references are pinned and retained. New C/D fixtures represent logical consumer baseline/target versions 0.3.0/0.4.0; both policy and defaults resources change. Each member has a fixed class/path, and every member validates the entire placement set before generation starts. Defaults occupy their own explicit folder so released Mod reverse-mapping remains unambiguous. Ordinary generation preserves existing customised output; it is not the persistent upgrade protocol.

Run `php tests/ScaffoldCheck.php <autoload.php> C` (also A/B/D) against a public Laravel 13 / Mod 0.9 install. The optional `misplace-defaults` argument checks that a mismatched second member refuses before writing the first. Each run creates a distinct synthetic filesystem fixture, not a real application consumer or an installed ownership record.

18 October 2026: Added immutable two-file C/D proof resources and whole-recipe placement validation; initial A/B identities retained.

The lifecycle adapter composes only public Mod `Artifact`, `Checkout`, `FilePlan` and `Maintenance` APIs. It runs on released `tey/mod` ^0.9 as installed from Packagist. It needs no local Mod source, overlay or source verifier. It refuses to run on any other Mod version. Mod publishes the Maintenance API; this package adds the blueprint, its identity and the host contract.

Bind `LifecycleHost` explicitly to application-owned code for `kit:update`. It supplies the app ID, frontend bindings, actual named input files, a `Lifecycle`, and a real operation/tenant acceptance closure. There is no default success callback. Required input IDs are `verification-policy`, `verification-code`, `dependency-lock`, `host-config`, `host-binding`, `host-operations` and `executor-provenance`. Executor provenance is required for every executor: write the exact typed `runtime()` result as JSON before preview. It is checked against the Mod version actually installed and fingerprinted as an execution input, not treated as proof on its own. These must name the actual policy, registered implementation, lock, configuration and binding files; include additional relevant package/verifier implementation inputs. The operation closure must be declared in the file named by host-operations; reflection rejects an unbound implementation before delivery. Package and resource checks run before effects and again before recording, including after the host operation callback. Host code cannot replace package dependency/resource checks.

Preview with `kit:update plan --target=C`, or `kit:update plan --features=foundation,defaults` to select features from a catalog host. Review its plan identity before `kit:update apply --target=C --intent=<stable-intent> --expected-plan=<identity> --exclusive-checkout`. Upgrade with `--original=C --target=D`, or add features with `--original-features=foundation --features=foundation,defaults`; retry an interrupted exact intent with `kit:update resume` and the same original/target/intent. On an upgrade, the original you name must rebuild exactly the installed artifact, features and bindings included; otherwise the command refuses before Mod does. Every refusal or noncompletion exits nonzero, including conflicts, failed verification and unresolved orphan candidates. Completed cold retries use the authoritative receipt without rerunning host callbacks. No force, semantic merge, migration execution or orphan deletion exists.

The artifact's inert metadata contains the pinned blueprint source, complete manifest and truthful empty applied-migration IDs. Requirements, migration declarations, exceptions and host ownership are provenance, not execution evidence. The generic Mod installed record remains the sole version authority, committed last. Executor provenance is separate from immutable artifact provenance. It is exposed in results and bound through the checked executor-provenance input in the authoritative installed commit. A changed executor and input can run a new upgrade intent against the unchanged historical artifact; they cannot reuse an old intent. The input is host configuration, never a competing installed-version record. All paths require an exclusively owned checkout. Advisory locking cannot protect against arbitrary editors; preserve/refuse conflicts and unresolved recovery files.

Run `php tests/CatalogCheck.php <autoload.php>` for external catalogs, stub trees and the catalog host, against the fixture catalog in `tests/fixtures/catalog`. Run `php tests/FeatureCheck.php <autoload.php>` for manifest validation and feature growth rules (no lifecycle API needed). Run `php tests/ScaffoldCheck.php <autoload.php> features:foundation,defaults` for a feature selection from the v0.1 fixture catalog. Run `php tests/LifecycleCheck.php <autoload.php>` for the lifecycle against the Mod in vendor: first adoption, plan and apply, additive upgrade, refused removal, an unowned path, resume, and a cold retry. `php tests/ResourceCheck.php <autoload.php>` tampers only fresh disposable package copies. Run `php tests/StarterCatalogCheck.php <autoload.php>` for the starter catalog: pins, the requires graph, stub compilation, ownership, `kit:list` and migration loading. Run `php tests/LibraryCheck.php <autoload.php>` for the feature library: its dependency graph against the starter's, autoloading, and the registry each selection builds. CI runs all of these on PHP 8.4 and 8.5. These callbacks and files are synthetic adapter evidence, not consumer operation/tenant/browser/database acceptance. `ScaffoldCheck.php` compares snapshots to actual public released scaffold output for every A/B/C/D release.

The tests install the blueprint's peer packages as dev dependencies, so the compatibility check runs on real released versions, including `tey/concierge` ^0.7.3 with Mod 0.9.


19 October 2026: Keep immutable blueprint identity independent of the current executor; bind verified executor provenance as an execution input and preserve same-intent staleness across new-executor upgrades.

19 October 2026: Added per-feature manifests, a no-baseline adopt path and an add-only feature upgrade on one growing artifact. The manifest key `modules` is now `groups`; Mod's own `Artifact::$modules` reads wait for Mod 0.8.

## The shell

The `shell` feature needs only core. It generates the signed-in shell, `pages/Workspace/Layout.vue` with its `Partials/`, and the guest shell, `Partials/GuestShell.vue`. A host with its own tenancy adopts `core` and `shell` and builds a `Tey\Kit\Features\Shell\Data\ShellData` itself. The shell feature shares the `shell` Inertia prop on every page, built by the bound `Tey\Kit\Features\Shell\ShellSource`: on a core and shell host, `PlainShell` (the product and the footer, plus the signed-in user's name, with no workspace), so the PublicShell's footer and CTA get their data without session-ui; with session-ui, its `PageShell`. Nothing in the kit flushes Inertia's shared props or shares `shell` again, so the prop has one producer, beside the host's own `HandleInertiaRequests` shares. A host that builds its own binds its own `ShellSource`. The host's one layout callback picks the shell: GuestShell for auth pages and whenever the shared user is null, `Layout.vue` otherwise. Pages declare no layout.

- **Layout slots.** `mark`, `extra`, `actions` (in the header, and in the drawer below `lg`), the default slot and `footer`, plus `bar`. The `bar` slot is for a compact control that must stay in the bar at every width, such as a search opener. It renders once, never in the drawer, and it shrinks before the switcher and the menu button do. Pass it through your layout wrapper rather than teleporting into the shell's markup: the shell's selectors are not an API.
- **SubNav.** `ShellData::$subNavigation` (`{ label, items: [{ label, url, current }] }`, or null) is the active module's pages, shown as a strip of links under the header. It scrolls sideways on a phone and brings the current page into view. A feature registers a module's pages with `Registry::modulePage($module, $ability, $label, $suffix, order:, active:)`, where `$module` is the suffix of the module's primary navigation link. `active` (here and on `navigation()`) is one path pattern under the workspace or a list of them, for a module that spans several prefixes; an entry `route:<name pattern>` (`route:trips.entries.*`) matches the current route's name instead, and a link is current when any entry matches. `PageShell` lists the pages the viewer may open while the module, or one of its pages, is current; the most specific match is the current one. SubNav is not the "Back to parent" strip (`layout.navigationHeader`), and the two can sit together.
- **GuestShell.** Props: `title`, `description`, `width` (`narrow` or `wide`), `backUrl` and `backLabel` (the labelled way back to your site; the shell guesses no URL), `appearance` (the light/dark switch, on by default) and `product` (else the shared `shell.product`). Slots: the card body, `footer` and `mark`. It needs no ShellData, user, workspace or auth route, so it also serves a cacheable public page or an error page that keeps its real status.
- **Types.** ShellData and ShellKind moved from `SessionUi\Data` to `Shell\Data`. The old PHP names are class aliases, and shell generates `resources/js/types/kit-shell.d.ts` aliasing the old TypeScript names, so a layout typed against either still checks.
- **Installed hosts.** An app that installed session-ui before the split moves the shell's files over with ordinary per-lineage updates, each planned, reviewed and applied with its own `--intent`: `kit:update --lineage=session-ui` releases them (session-ui goes to an interim version that does not yet require shell), `kit:update --lineage=shell` installs shell at exactly those bytes, claiming them without a write, and then `--lineage=shell` again updates it to its target and `--lineage=session-ui` again finishes. Each result's `next` names the following run. If you have edited a moved file, the first step writes nothing and prints the diff from the installed file to yours: put your changes aside, restore the file, run the steps, then reapply them. Needs tey/mod with removals (main, 0.11). A feature's `moved` (`{path, to}`) and `removed` (`{path, policy}`, with `delete-if-unchanged` or `keep-as-host`) list the paths an earlier version owned; an update that drops a path neither lists is refused.
- **Replacing the user menu or switcher.** The Layout imports `UserMenu` and `WorkspaceSwitcher` only through `@kit/shell`, an alias for the generated `resources/js/pages/Workspace/Partials/index.ts`. Add it beside `@kit/ui` (`'@kit/shell': path.resolve(__dirname, 'resources/js/pages/Workspace/Partials/index.ts')`, and the same in `tsconfig.json` paths). To replace one, point the alias at your own module that re-exports the rest.
- **Tokens.** The shell's colours (`--shell-bar`, `--shell-subbar`, `--shell-band`, `--shell-mark-*`) come from the host's `resources/css/app.css`, as in the starter. A host without them sets them there. `--shell-ground` is the Layout's page ground: unset, it is the theme's background.
- **Layout options.** Each is off by default.
  - `wordmarkLabel` names the wordmark link, for example with a tenant's name. Without it the product's name does.
  - `guestHeader` draws the header for a guest too, with no user menu and no drawer.
  - With no pages to list (no `nav` or `drawer-nav` slot and an empty `navigation`), there is no drawer. The account menu and `actions` stay in the bar at every width.

## Auth-only session-ui and workspace-ui

`session-ui` is the sign-in, and needs only core and shell: `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` (Fortify's `password.confirm`), `Auth/TwoFactor` (the challenge) and `Auth/VerifyEmail`, with Fortify's routes behind them, plus the person's welcome steps (`Welcome/Timezone`, `Welcome/Photo`), the avatar routes, switch account, the passkey offer, the per-person Workbench layouts and its two tables (security events, user state). It installs no workspace, access, Concierge, Pennant or multitenancy, so a single-owner site signs in with core, shell and session-ui alone. The kit has no Register page: registration is closed (accounts come from invitations, or from the host). `workspace-ui` requires session-ui (and workspaces, access and shell) and holds the workspace pages: the kit's home (`kit.home`) and the dashboard, search, the settings and account landing pages (`Workspace/Sections`), Settings › People, `Settings/Layout.vue`, the account pages under `/me` (`Workspace/Account`), the placeholders, `Workspace/Error`, `Workspace/Index` and `Provision/NoWorkspace`, with the dashboard widgets, the people search provider, the error pages and the workspace opener; and, since 0.6.0, the invitation link (`Auth/Invitation/Show`, its acceptance, the branded invitation email), the owner's workspace-timezone welcome step (`Welcome/WorkspaceTimezone`) and the first-run demo's sign-in. notes requires workspace-ui (its dashboard widget and trail). workspace-ui also has the owner's Transfer ownership and Delete workspace actions and the person's Delete account. Each deletion dispatches lifecycle events, after the commit, so a host can purge its own data in a queued listener ([docs/lifecycle-events.md](docs/lifecycle-events.md)).

- **Auth-only host.** `php artisan kit:add session-ui` (it brings core and shell; add workspaces and access yourself if you want them, and not workspace-ui). The kit serves no page at `kit.home`: that is the app's own, and after sign-in Fortify sends the person there (the kit's `fortify.home` default is `kit.home` without workspace-ui; a published `config/fortify.php` decides for itself). Serve your signed-in landing at `kit.home` (`/` unless you set `KIT_HOME`). The last welcome step lands there too, and the welcome pages' trails start at themselves. Your own error page renders errors (the kit's is workspace-ui's). There is no invitation page: invitations are workspace-ui's. Add workspace-ui later with `kit:add workspace-ui`.
- **Same routes, same names.** workspace-ui's routes are the ones session-ui had, with the same names, loaded right after session-ui's and governed by `kit.routes.session-ui` (prefix, middleware, name, enabled), so nothing a host configured moves. Its classes keep their `Tey\Kit\Features\SessionUi\...` names. A selection with workspace-ui generates exactly the files session-ui generated before the split, at the same bytes; only their owner changes.
- **Installed hosts.** An app that installed session-ui before the split keeps its workspace pages on after `composer update`: workspace-ui is carried, for its lineages and for a `kit.features` list, while session-ui's installed lineage still owns those pages or has released them, and once workspace-ui's lineage is installed. Move the files with the per-lineage updates of the shell split, each planned, reviewed and applied with its own `--intent`: `kit:update --lineage=session-ui` releases the 22 pages (nothing is written or deleted), `--lineage=workspace-ui` claims them at exactly those bytes with no write, `--lineage=workspace-ui` again updates it to its target and `--lineage=session-ui` again finishes. Then `--lineage=notes`, if you have it (it now requires workspace-ui). Each result's `next` names the following run; the CHANGELOG has the exact commands. If you have edited a moved page, the first step writes nothing and prints the diff from the installed file to yours: put your changes aside, restore the file, run the steps, then reapply them. A `kit.features` list in `config/kit.php` should name `workspace-ui` after `session-ui`.
- **The 0.6.0 move.** The invitation page and the workspace-timezone step moved from session-ui to workspace-ui, with their routes and hooks. An app with both installed re-pins once: `kit:update --lineage=session-ui` releases the two files and `--lineage=workspace-ui` claims them at their bytes, with nothing written or deleted. Only the first split's pages carry workspace-ui: for an app with session-ui alone, `kit:update --lineage=session-ui` releases the two files in one run and keeps them on disk, unchanged, as the app's (listed under `kept`); nothing is deleted and no workspace-ui step follows. workspace-ui is needed only for invitations (`kit:add workspace-ui`).

## Scrolling lists

`ScrollList` (the `@kit/ui` component) renders a `Tey\Kit\Features\Core\Lists\ScrollListViewModel`: a cursor-paged list that loads more as the viewer scrolls, with an always-present "Load more" button. A list declares its `key()`, `title()`, an already ordered and tenant-scoped `query()`, a `row()` mapping and its first-use `empty()` state; the page passes `$list->items()` as the prop named by the key. The continuation is encrypted and binds the viewer, workspace, list, selection and mode, so a replayed or tampered one is a 400 and never a first page. This is the cursor-only, fixed-selection, count-free subset: sorts, segments, totals, polling, page and simple paging modes and `ScrollWidgetViewModel` from the spec are **not** included. Cursor order is deterministic but not a snapshot: edited or moved records can move between windows.

## Use your own components

Kit pages and the shell import every UI primitive (the form set, buttons, cards, menus) only through one alias, `@kit/ui`, as in `import { Field, Input, Button } from '@kit/ui'`. By default the alias resolves to the kit's own set: `core` generates it into your app at `resources/js/components/Kit/` (`index.ts`, `contract.ts` and `Form/*`), built on the shadcn-vue primitives in `@/components/ui` (button, input, label, checkbox, dropdown-menu, sheet, from Laravel's MIT Vue starter kit) and Reka UI. Plain Tailwind otherwise.

Pages follow the ViewModel standard: a page's props are one typed ViewModel (`Tey\Kit\Features\Core\ViewModels\PageViewModel`; every public method with no required parameters is a prop, with a declared return type and a docblock shape for arrays), and the generated TypeScript is read from those methods, so `defineProps<EditClientViewModel>()` restates nothing. `AdHocViewModel` is for a small page (three props at most, no action or permission prop, one render site; the guard fails one that outgrows that). Data classes are values and forms, never the page. There are two producers and two keys: `shell` (the product and viewer, the workspace switcher, the primary navigation and a small shell-only `can` map, shared by the middleware) and the page's own `layout()` (`layout.navigation` for the section navigation, `title`, `documentTitle` and `breadcrumbs`, all decided on the server). Pages declare no layout: the nearest folder `Layout.vue` (`pages/Workspace/Settings`, `pages/Workspace/Account`) renders the section navigation and stays mounted while you move between its sections. Permission checks are memoized per request.

The brand is `config('kit.brand')` (the KIT_BRAND_* variables): the product's name and logomark, the accent and its companions, the sender addresses and the footer lines. The web and mail footers omit any part that is empty (no dangling separator, no "Built by" with nothing after it). `KIT_BRAND_PRIVATE=true` is for a private app with no public legal entity: the legal name, postal address, Terms and Privacy are left out of both footers, and `Brand::placeholders()` (what `kit:brand` reports as still to fill in) no longer asks for them. Beyond the accent, the brand can set surfaces (`background`, `card`, `border`, `muted`, each with a `_dark` twin), link colours (`--brand-link`), the button, header and marker roles apart from `style`, a rule under the header, a dark logomark and a lockup; every one is optional and unset prints nothing (see `config/kit.php`). `KIT_BRAND_PRESETS` and the local-only `?brand=` switch compare accent variants side by side.

The account pages under `/me` (Profile, Password, Security) use Fortify's own features and actions, wired by the kit: the name and email (a changed address stays unverified until its link is followed, and the page says so), the password (other sessions are signed out afterwards), and two-factor authentication (the password is asked for again, a code from the authenticator turns it on, recovery codes are shown once, and turning it off or regenerating the codes confirms first). The host's user model needs Fortify's `TwoFactorAuthenticatable` trait and `MustVerifyEmail`, and the `users` table Fortify's two-factor columns (the starter has both). Signed-in devices can be listed and ended only with `SESSION_DRIVER=database`; the page says so otherwise.

**Sessions for MCP:** set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment, not the platform default. Passport keeps the consent request in the session, which outgrows a cookie session's 4 KB, so consent would fail. On `SESSION_DRIVER=cookie` the consent routes refuse with an error page and log why, and the mcp plan flags it as attention. The tey/standards preflight `install.sessions` also checks it at deploy. The security log is the kit's `kit_security_events` table, written from the sign-in, failed sign-in, password and two-factor events; it belongs to the user, is never shown in a workspace and is kept.

Forms follow one shape: Inertia's `<Form>` (uncontrolled, so every control submits a native named value) with the kit's `Field name`, ending in `FormCard` or `FormActions`: the bar with the form's status, Cancel and the submit button, sticky, with a leave-page warning while the form is dirty; a destructive action goes through `ConfirmDialog`, and Delete lives in its own danger-zone section, never in the bar. The peer dependency is `@inertiajs/vue3` ^3 (`useFormContext` needs 2.3.9 or later).

The kit's Reka components need `reka-ui` 2.11.0 or later (`InputSelect` does not typecheck on earlier releases). Core lists it under `requires.npm`; the host's `package.json` must provide it, because `kit:add` does not install npm packages. Its preview checks the declared range and flags a missing or older one.

Add the alias in `vite.config.ts` and `tsconfig.json`:

```ts
// vite.config.ts
resolve: { alias: { '@kit/ui': path.resolve(__dirname, 'resources/js/components/Kit/index.ts') } }
```

```json
// tsconfig.json, compilerOptions
"paths": { "@kit/ui": ["./resources/js/components/Kit/index.ts"] }
```

To use your own components, point `@kit/ui` at a module of yours that exports the same names, with the props in `resources/js/components/Kit/contract.ts`. The kit's pages import these from it (mcp's Authorize page imports `InputSelect`, for instance), so your module must export each of them: <!-- kit-ui-names -->`ActivityStream`, `Alert`, `AppearanceToggle`, `Avatar`, `BlockStack`, `Button`, `DataTable`, `Dialog`, `Dropdown`, `DropdownConfirm`, `DropdownItem`, `DropdownMenu`, `DropdownMenuContent`, `DropdownMenuItem`, `DropdownMenuLabel`, `DropdownMenuSeparator`, `DropdownMenuTrigger`, `Field`, `FileInput`, `FormActions`, `FormCard`, `Input`, `InputCheckbox`, `InputSelect`, `Modal`, `Page`, `PageEnd`, `PasskeyOffer`, `PasswordInput`, `Placeholder`, `ScrollList`, `SearchBox`, `Section`, `SectionIcon`, `Select`, `SettingsFrame`, `Sheet`, `SheetContent`, `SheetDescription`, `SheetTitle`, `SheetTrigger`, `Textarea`, `TimezoneSelect`, `Toaster`, `browserTimezone`, `opensSearch`<!-- /kit-ui-names -->. Kit pages then render your components, with no forking of the pages. Re-export what you do not replace: `export * from '@/components/Kit'`, then your own named exports.

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

The shell feature's host steps point this out, along with the `@kit/shell` and `@kit/ui` aliases. Transforming the kit's `src` with your own transformer isn't needed, and a transformer that writes one flat module can't produce these namespaces. If yours already writes them (the global-namespace writer over `vendor/tey/kit/src`), leave the include out, since both would declare each type twice. The dashboard's view model is typed `WorkspaceDashboardViewModel`, so it never collides with an app's own `DashboardViewModel`.

A host that copies the file instead (`resources/js/types/kit.d.ts`) is told when a release outdates the copy, with the types it lacks. The kit's CI keeps `types/kit.d.ts` in step with its classes (`composer test:kit-types`; `php bin/kit-types.php` rewrites it).

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

Before tagging a release, run `php bin/catalog-history.php` and commit `resources/features/history.json`. It records each tagged release's file hashes, which `kit:adopt` uses to recognise what a pre-lifecycle app installed, plus a `current` entry for the catalog being tagged. `composer test:adopt-in-place` (run in CI) fails when `current` no longer matches the live catalog, so any change to a feature's stubs or manifest regenerates it in the same PR. `php bin/catalog-history.php --check` runs the same comparison on its own. A change to a feature's stubs or manifest also changes its pin: `php bin/catalog-pins.php` prints every feature's current identity next to its pin and marks it `ok` or `changed`. Copy a changed one into `src/Catalog.php` and `tests/StarterCatalogCheck.php`, then regenerate the history. With `--check` (a CI step) it exits 1 while any pin is stale.
