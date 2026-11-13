# Releasing

**Features affected.** Since 0.18 a fix in a stub never reaches the files an app already has: they are the app's own. When you rename `## Unreleased` to the new version, run `php bin/release-lineages.php` and put its output first under the new heading, so an app knows which features to review. It compares the feature pins in `src/Catalog.php` at HEAD with the newest `vX.Y.Z` tag before HEAD (or `php bin/release-lineages.php <from-ref> [<to-ref>]`) and prints `Features affected: <features>`, in canonical order, or `Features affected: none.` A new feature gets its own `New features:` line. It exits 1 when either ref's `src/Catalog.php` cannot be read.

**Form payloads.** A change to what a kit form submits (a field removed or renamed, or a form's fixture removed) is breaking: release it as a minor, not a patch, with an upgrade note, and add a line `Form payload: breaking` under `## Unreleased` in CHANGELOG.md. Added fields are not breaking, since the server drops undeclared fields. `php bin/form-payload-check.php <previous-tag>` compares the field names in `tests/fixtures/form-payloads/` with that release; the `Form payloads` CI job runs it on every pull request against the merge base with main.

**Features affected.** Each entry under `## Unreleased` that changes what a feature ships or does says which features, on its own line under the entry: `Features affected: <ids>`, the feature ids in canonical order (`core, workspaces, access, settings, shell, session-ui, workspace-ui, mcp, notes, finalize, deliver`), or `Features affected: none` for a change outside every feature (a command, the docs, CI). From 0.18 an app's features are its `kit.features` list, so this line is how an app tells whether a release touches what it runs. For example:

```markdown
- **The invitation page names the inviter.**
  Features affected: workspace-ui
```
