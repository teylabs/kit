# Changelog

## Unreleased

### Changed

- Renamed from `tey/starter-blueprint` to `tey/kit`. This package has never been published and nothing depends on it, so there are no aliases. Namespace `Tey\StarterBlueprint` is now `Tey\Kit`, `BlueprintServiceProvider` is `KitServiceProvider`, the config key `starter-blueprint` is `kit`, `starter:maintain` is `kit:update` (same arguments and options), and the scaffold `tey/starter-foundation` (command `starter:foundation`) is `tey/kit` (command `kit:foundation`). Other class names are unchanged.
- Pinned identities changed with the scaffold id: A `sha256:21bd1704…45e2` → `sha256:686b417b…5c74b`, B `sha256:70af6427…726c` → `sha256:857fc187…2b3b`, C `sha256:85405850…a3ff` → `sha256:18e53f09…7bdb`, D `sha256:a85ea6fe…7aaf` → `sha256:777333b1…dd0f`; feature `foundation` `sha256:8e2e55ee…4054` → `sha256:40bbc9be…3541`, `defaults` `sha256:fb252b8b…2e43` → `sha256:71662b1b…ec7f6`; all features `sha256:a7324f25…475b9e` → `sha256:db4358fe…956675`.
- CI runs every `composer test:*` script on PHP 8.4 and 8.5, and a Privacy job.

### Added

- `kit:list`: prints the catalog's features with what each requires and owns. Read-only.
- `Blueprint::entries()`: the same listing as data.
- `bin/privacy-check.sh`, `AGENTS.md` and `CLAUDE.md`.
- `Catalog`: a feature catalog outside the package, with its own pinned identities. `Catalog::at($root, $pins, $basePin)` builds one, `Catalog::package()` is this package's own. `Blueprint::features()`, `Blueprint::all()` and `Blueprint::featureIds()` take an optional catalog and default to the package catalog. An external catalog's identities are checked as strictly as the package's.
- `files_from` in a feature manifest: a tree of stubs expands into generated files, sorted, with symlinks, escapes and duplicate targets refused. The feature identity covers every expanded stub.
- `CatalogHost`: a `LifecycleHost` that also implements it supplies the catalog `kit:update` builds its blueprints from.
- A seven-feature fixture catalog and `tests/CatalogCheck.php`, run in CI.

## v0.1.0

### Added

- Per-feature manifests, `foundation` and `defaults`, each declaring its owned files, requirements, conflicts and host-owned paths. Every feature is content-addressed into the blueprint's source reference, and the whole app is all features.
- First adoption with no existing baseline, and add-only upgrades as the feature selection grows. Removing a feature is refused with a clear message.
- `starter:maintain {plan|apply|resume}` with `--original`, `--target`, `--features`, `--original-features`, `--intent`, `--expected-plan` and `--exclusive-checkout`. Every refusal or incomplete run exits nonzero.
- The `tey/starter-foundation` scaffold for previewing and generating the files through Mod's public scaffolds.
- The `LifecycleHost` contract an app implements for its app ID, bindings, input files and operation check, plus executor provenance and input fingerprints bound into each run.
- An upgrade's original must equal the installed artifact exactly, metadata included, or it is refused before Mod runs.
- Pinned release fixtures A–D: A `sha256:21bd1704…45e2`, B `sha256:70af6427…726c`, C `sha256:85405850…a3ff`, D `sha256:a85ea6fe…7aaf`; feature `foundation` `sha256:8e2e55ee…4054`, `defaults` `sha256:fb252b8b…2e43`.
- Requires `tey/mod` ^0.8 and `tey/concierge` ^0.7.2. CI proves the lifecycle on PHP 8.4 and 8.5 against the released packages: first adoption, plan and apply, additive upgrade, refused removal, an unowned path, resume and a cold retry. Adopting into an app that already has files the blueprint would own is refused until Mod can claim existing files.
