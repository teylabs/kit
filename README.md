> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

# tey/kit

Feature kit for tenant-aware Laravel apps, built for the Tey Labs fleet. An app adopts features from a catalog with `kit:add`, one Mod lineage per feature, updates each with `kit:update --lineage=<feature>`, and lists what the catalog offers with `kit:list`. `kit:status` is planned and does not exist yet. The package carries an opinionated versioned application contract and a public Mod scaffold pack. A/B and C/D are local synthetic release fixtures, not published versions. Runtime tenancy/membership/settings stay in maintained packages and host bindings.

The scaffold id is `tey/kit` and its command is `kit:foundation`. This package was renamed before first publication; there are no aliases. See the changelog.

## Features

The app is a set of features. Each feature has a manifest at `<catalog>/<id>/feature.json` with its id, owned paths, generated files (with their stubs), requirements (other features and packages), conflicts and host-owned paths. `<catalog>/blueprint.json` holds what every selection shares: the artifact name, version, groups, migration order and verification policy.

The package catalog, `Catalog::package()`, is the starter catalog: core, workspaces, access, settings, session-ui, mcp and notes ([docs/catalog.md](docs/catalog.md)). The v0.1 features `foundation` (the policy file) and `defaults` (the defaults file, which requires `foundation`) are a regression fixture in `tests/fixtures/v01`, loaded by `tests/fixtures/v01.php`; they are not in the package catalog. The whole app is every feature. Select features with `Blueprint::features(['foundation'], $catalog)`, `Blueprint::all($catalog)`, or `config('kit.scaffold_features')` for the v0.1 scaffold fixture, which builds from the bound host's catalog when it implements `CatalogHost`.

A selection is checked before anything is built. It is refused when a feature is unknown or listed twice, a required feature is missing, two features conflict, a file sits outside its feature's owned paths or inside a host-owned path, two features own the same path, or two features disagree on a package constraint.

Each feature is content-addressed: its manifest bytes and its stub bytes are pinned in `Blueprint`. A selection's source reference covers the composed manifest, every resource byte and every feature's pinned identity. The order you list features in does not change it. The selection is recorded in the manifest and in the artifact metadata (`blueprint_features`).

Mod refuses changes to an artifact's name, groups or bindings, and refuses removals. `kit:add` installs each feature as its own lineage (`tey/kit:<feature>`). The release fixtures and `kit:update --features` keep the older single-lineage path, one growing artifact:

- A first adoption has no baseline. The original is an empty artifact with the same name, groups, bindings and checks as the target, and no metadata, as Mod builds its own. Existing paths are refused as unowned, even when the bytes match.
- Adding a feature is an upgrade from the old selection to a larger one on the same artifact.
- Removing a feature is refused with a clear message, before any other check.

Per-feature artifacts need an additive Mod API and are not part of 0.1. The manifest shape is ready for it.

## Feature library

The starter's features run from the library under `src/Features/<Feature>` (`Tey\Kit\Features\...`): core, workspaces, access, settings, session-ui, mcp and notes. `Tey\Kit\Features\FeaturesServiceProvider` (auto-discovered) registers the providers of the adopted features, `config('kit.features')` or else the installed record's `blueprint_features`, loads their migrations from `database/migrations/kit/<feature>`, and fills the `Registry` they share: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, the connection guard and error codes. A selection with an unknown feature, a duplicate or a missing requirement is refused.

Routes and config ship in the library: no feature generates a config file. The kit's defaults merge in with nothing published; `php artisan vendor:publish --tag=kit-config` writes `config/kit.php` as the app's own file, which kit updates never touch. `kit.routes.<feature>` takes `enabled`, `prefix`, `middleware` and `name` (a route-name prefix). Other keys: `kit.models.user` (default: the `users` auth provider's model), `kit.capabilities` (capability => on), `kit.invitation_redirect` (a route name taking `workspace`; default: the first navigation link) and `kit.mcp.connection_ability` (default `settings.read`). A delegated (MCP) principal is refused unless mcp is adopted. Controller redirects still use `/workspaces/...` paths, so a route `prefix` does not move them yet (kit #12).

## Listing the catalog

`kit:list` prints each feature of the catalog: its id, the features it requires and the paths it owns. `--json` prints the same as JSON. It is read-only and uses the bound host's catalog when the host implements `CatalogHost`, else the package catalog.

## External catalogs

`Catalog::package()` is this package's own catalog. A consuming package can ship its own. A `Catalog` is a directory with a `blueprint.json` and a `<feature>/feature.json` per feature, plus the pinned identity of each:

```php
$catalog = Catalog::at($root, [
    'core' => 'sha256:…',
    'notes' => 'sha256:…',
], basePin: 'sha256:…');

Blueprint::features(['core', 'notes'], $catalog);
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
