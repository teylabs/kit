> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

# tey/kit

Feature kit for tenant-aware Laravel apps, built for the Tey Labs fleet. An app adopts features from a catalog with `kit:add`, one Mod lineage per feature, updates each with `kit:update --lineage=<feature>`, and lists what the catalog offers with `kit:list`. `kit:status` is planned and does not exist yet. The package carries an opinionated versioned application contract and a public Mod scaffold pack. A/B and C/D are local synthetic release fixtures, not published versions. Runtime tenancy/membership/settings stay in maintained packages and host bindings.

## Quick start

```sh
composer require tey/kit
php artisan kit:install
```

`kit:install` writes the app-owned host binding `kit:add` needs: `app/Kit/KitHost.php`, the operation check in `app/Kit/operations.php`, `config/kit-lifecycle.php` (verification policy and app id), `config/kit-executor.json` (the Mod runtime) and `app/Providers/KitHostServiceProvider.php`, and adds that provider to `bootstrap/providers.php`. Those files are yours: no feature owns them, and `kit:add` and `kit:update` never touch them. It refuses to overwrite any existing file, and a second run changes nothing.

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

The scaffold id is `tey/kit` and its command is `kit:foundation`. This package was renamed before first publication; there are no aliases. See the changelog.

## Fonts

An app picks one font set by name. Put the component in your layout head:

```blade
<head>
    <x-kit::fonts />
</head>
```

It links the set's fonts from fonts.bunny.net and sets four variables on `:root`: `--font-sans` (text), `--font-serif` (display), `--font-mono` and `--font-display-weight`, each with system fallbacks. Pages and the Tailwind theme use only those variables. Tailwind v4's `font-sans`, `font-serif` and `font-mono` read them already, so don't set font families anywhere else. For display text, use `font-serif` with `font-weight: var(--font-display-weight)`.

| Set | Text (`--font-sans`) | Display (`--font-serif`) | Mono (`--font-mono`) |
| --- | --- | --- | --- |
| `instrument` (default) | Instrument Sans 400/500/600 | Instrument Serif 400 and italic, regular | IBM Plex Mono 400/500 |
| `instrument-inter` | Inter 400/500/600 | Instrument Sans 600, semibold | IBM Plex Mono 400/500 |
| `hanken` | Hanken Grotesk 400/500/600 | DM Serif Display 400, regular | DM Mono 400/500 |

To switch sets, set `KIT_FONTS` in `.env`, or `fonts` in a published `config/kit.php`:

```sh
KIT_FONTS=hanken
```

An unknown set name is refused when the component renders, and the error lists the three sets.

## Features

The app is a set of features. Each feature has a manifest at `<catalog>/<id>/feature.json` with its id, owned paths, generated files (with their stubs), requirements (other features and packages), conflicts and host-owned paths. An optional `host_steps` names changes only the host can make. The one kind is `user_model`: the user model must implement `implements` and use the trait `uses`. `kit:add` checks each one by reflection for the features it adds. An unmet step makes the plan not executable, and `--resume` refuses it too. `<catalog>/blueprint.json` holds what every selection shares: the artifact name, version, groups, migration order and verification policy.

The package catalog, `Catalog::package()`, is the starter catalog: core, workspaces, access, settings, session-ui and mcp ([docs/catalog.md](docs/catalog.md)). The v0.1 features `foundation` (the policy file) and `defaults` (the defaults file, which requires `foundation`) are a regression fixture in `tests/fixtures/v01`, loaded by `tests/fixtures/v01.php`; they are not in the package catalog. The whole app is every feature. Select features with `Blueprint::features(['foundation'], $catalog)`, `Blueprint::all($catalog)`, or `config('kit.scaffold_features')` for the v0.1 scaffold fixture, which builds from the bound host's catalog when it implements `CatalogHost`.

A selection is checked before anything is built. It is refused when a feature is unknown or listed twice, a required feature is missing, two features conflict, a file sits outside its feature's owned paths or inside a host-owned path, two features own the same path, or two features disagree on a package constraint.

Each feature is content-addressed: its manifest bytes and its stub bytes are pinned in `Blueprint`. A selection's source reference covers the composed manifest, every resource byte and every feature's pinned identity. The order you list features in does not change it. The selection is recorded in the manifest and in the artifact metadata (`blueprint_features`).

Mod refuses changes to an artifact's name, groups or bindings, and refuses removals. `kit:add` installs each feature as its own lineage (`tey/kit:<feature>`). The release fixtures and `kit:update --features` keep the older single-lineage path, one growing artifact:

- A first adoption has no baseline. The original is an empty artifact with the same name, groups, bindings and checks as the target, and no metadata, as Mod builds its own. Existing paths are refused as unowned, even when the bytes match.
- Adding a feature is an upgrade from the old selection to a larger one on the same artifact.
- Removing a feature is refused with a clear message, before any other check.

`kit:add` claims existing files only from an explicit, reviewed list. Its preview lists each existing file whose bytes already equal a feature's file as `claimable` and leaves it unowned, so the plan is not executable. Review each one, then list it with `--claim=<path>` (repeat the option, or separate paths with commas) in both the preview and `--apply`. The claim list is part of the plan identity. Only listed paths are claimed, without a write. A listed file with different bytes is a conflict, and an unlisted one is refused as unowned. A claim that names no file of a feature being added is refused.

When an apply stops, `kit:add <features> --resume --intent=<id>` (with the same `--claim` list) finishes the interrupted feature, then adds every remaining feature of the request in requires order. It prints which features are done and which remain, and exits nonzero unless all of them completed. While a lineage has an unfinished update, `kit:add` and `kit:update` refuse with `pending:<lineage>`, then print that lineage's intent and the exact resume command.

Per-feature artifacts need an additive Mod API and are not part of 0.1. The manifest shape is ready for it.

## Feature library

The starter's features run from the library under `src/Features/<Feature>` (`Tey\Kit\Features\...`): core, workspaces, access, settings, session-ui and mcp. `Tey\Kit\Features\FeaturesServiceProvider` (auto-discovered) registers the providers of the adopted features, `config('kit.features')` or else the installed record's `blueprint_features`, loads their migrations from `database/migrations/kit/<feature>`, and fills the `Registry` they share: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, the connection guard and error codes. A selection with an unknown feature, a duplicate or a missing requirement is refused.

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

## Use your own components

Kit pages and the shell import every UI primitive (the form set, buttons, cards, menus) only through one alias, `@kit/ui`, as in `import { Field, Input, Button } from '@kit/ui'`. By default the alias resolves to the kit's own set: `core` generates it into your app at `resources/js/components/Kit/` (`index.ts`, `contract.ts` and `Form/*`), built on the shadcn-vue primitives in `@/components/ui` (button, input, label, checkbox, dropdown-menu, sheet, from Laravel's MIT Vue starter kit) and Reka UI. Plain Tailwind otherwise.

Pages follow the ViewModel standard: a page's props are one typed ViewModel (`Tey\Kit\Features\Core\ViewModels\PageViewModel`; every public method with no required parameters is a prop, with a declared return type and a docblock shape for arrays), and the generated TypeScript is read from those methods, so `defineProps<EditClientViewModel>()` restates nothing. `AdHocViewModel` is for a small page (three props at most, no action or permission prop, one render site; the guard fails one that outgrows that). Data classes are values and forms, never the page. There are two producers and two keys: `shell` (the product and viewer, the workspace switcher, the primary navigation and a small shell-only `can` map, shared by the middleware) and the page's own `layout()` (`layout.navigation` for the section navigation, `title`, `documentTitle` and `breadcrumbs`, all decided on the server). Pages declare no layout: the nearest folder `Layout.vue` (`pages/Workspace/Settings`, `pages/Workspace/Account`) renders the section navigation and stays mounted while you move between its sections. Permission checks are memoized per request.

The brand is `config('kit.brand')` (the KIT_BRAND_* variables): the product's name and logomark, the accent and its companions, the sender addresses and the footer lines. The web and mail footers omit any part that is empty (no dangling separator, no "Built by" with nothing after it). `KIT_BRAND_PRIVATE=true` is for a private app with no public legal entity: the legal name, postal address, Terms and Privacy are left out of both footers, and `Brand::placeholders()` (what `kit:brand` reports as still to fill in) no longer asks for them. `KIT_BRAND_PRESETS` and the local-only `?brand=` switch compare accent variants side by side.

The account pages under `/me` (Profile, Password, Security) use Fortify's own features and actions, wired by the kit: the name and email (a changed address stays unverified until its link is followed, and the page says so), the password (other sessions are signed out afterwards), and two-factor authentication (the password is asked for again, a code from the authenticator turns it on, recovery codes are shown once, and turning it off or regenerating the codes confirms first). The host's user model needs Fortify's `TwoFactorAuthenticatable` trait and `MustVerifyEmail`, and the `users` table Fortify's two-factor columns (the starter has both). Signed-in devices can be listed and ended only with `SESSION_DRIVER=database`; the page says so otherwise. The security log is the kit's `kit_security_events` table, written from the sign-in, failed sign-in, password and two-factor events; it belongs to the user, is never shown in a workspace and is kept.

Forms follow one shape: Inertia's `<Form>` (uncontrolled, so every control submits a native named value) with the kit's `Field name`, ending in `FormCard` or `FormActions`: the bar with the form's status, Cancel and the submit button, sticky, with a leave-page warning while the form is dirty; a destructive action goes through `ConfirmDialog`, and Delete lives in its own danger-zone section, never in the bar. The peer dependency is `@inertiajs/vue3` ^3 (`useFormContext` needs 2.3.9 or later).

Add the alias in `vite.config.ts` and `tsconfig.json`:

```ts
// vite.config.ts
resolve: { alias: { '@kit/ui': path.resolve(__dirname, 'resources/js/components/Kit/index.ts') } }
```

```json
// tsconfig.json, compilerOptions
"paths": { "@kit/ui": ["./resources/js/components/Kit/index.ts"] }
```

To use your own components, point `@kit/ui` at a module of yours that exports the same names (`Button`, `Checkbox`, `Field`, `FormActions`, `Input`, `Section`, `Select`, `Textarea`, `TimezoneSelect`, `DropdownMenu*`, `Sheet*`) with the props in `resources/js/components/Kit/contract.ts`. Kit pages then render your components, with no forking of the pages. Re-export what you do not replace: `export * from '@/components/Kit'`, then your own named exports.

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

## Computed values and their working

`ComputedValue` shows a figure the server calculated and lets the reader open how it was reached. The server sends a `Tey\Kit\Explanation\ExplanationDocument` (Laravel Data), and each root `ExplanationNode` carries its display string, exact minor units, inputs with their sources, the rule applied, rounding and allocation. The kit validates the document's structure and refuses floats, dangling operands, unsafe links and oversized trees. Arithmetic, reconciliation and authorisation stay with the app. The browser only displays the strings it is given.

```vue
<ComputedValueScope scope-key="invoice" remember>
    <ComputedValueToggle />
    <ComputedValue :node="explanation.roots[0]" label="Invoice total" placement="overlay" />
</ComputedValueScope>
```

`placement` is `inline` (the default), `table-row` or `overlay` (a popover, or a bottom sheet on a phone). `ComputedValueRoot` is the headless version, for your own markup. The `Explanation*` types come from `@kit/ui`. They are generated from the PHP classes by `php bin/generate-explanation-ts.php`, and `--check` fails when they are stale. Never edit the generated file by hand.
