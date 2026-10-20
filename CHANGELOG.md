# Changelog

## Unreleased

### Added

- Permissions. A feature (or an app) declares a permission and its default Concierge roles on the registry: `$registry->permission('workspace.settings.update', ['owner', 'admin'], capability: 'workspace.settings', abilities: ['settings.write'])`. The kit declares `workspace.settings.update` and `workspace.members.manage`. `php artisan kit:permissions:sync` creates the missing permissions and grants the defaults, re-runs change nothing, never revokes, and reports custom grants, undeclared permissions and default roles that do not exist (the last exits nonzero). Roles stay Concierge's, per workspace; the kit adds no role system. `WorkspacePolicy` (`view`, `update`, `manageMembers`) checks active membership, the role grant in the workspace's team context and the permission's Pennant capability, all three. `Permissions::flags()` gives a page its `can` map from those checks. `Access::authorize()` decides an ability a permission replaces (`settings.write`) by the permission, not by ownership; other `.write` abilities stay owner-only. The settings page's `canWrite` comes from the policy. Run the sync after `migrate` on deploy. A default is granted once: the `access` feature's new `kit_permission_defaults` migration records which defaults the sync has applied, so a default someone removed is not granted again, and one that is new since the last sync is. `HandleInertiaRequests` shares the current workspace's `can` map. `composer test:permissions` (registry, sync, policy, flags) and `composer test:permissions-http` (settings update over HTTP on real Concierge tables and spatie teams: admin allowed after the sync, member 403, capability off 403) run on SQLite memory. Run in CI. Re-pinned: package `access` `sha256:5030011a…e38b0` → `sha256:26bbe931…86528` (a new migration stub; `kit:update` delivers it).
- Font sets (#43). `kit.fonts` (`KIT_FONTS`, default `instrument`) names one of three sets: `instrument`, `instrument-inter` or `hanken`. `<x-kit::fonts />` in the layout head emits that set's fonts.bunny.net links and a `:root` block with `--font-sans`, `--font-serif` (display), `--font-mono` and `--font-display-weight`, with system fallbacks. An unknown set name is refused. `composer test:fonts` renders each set, checks the refusal and checks that pages name no font family but those variables. Run in CI.

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
