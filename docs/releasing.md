# Releasing

**Form payloads.** A change to what a kit form submits (a field removed or renamed, or a form's fixture removed) is breaking: release it as a minor, not a patch, with an upgrade note, and add a line `Form payload: breaking` under `## Unreleased` in CHANGELOG.md. Added fields are not breaking, since the server drops undeclared fields. `php bin/form-payload-check.php <previous-tag>` compares the field names in `tests/fixtures/form-payloads/` with that release; the `Form payloads` CI job runs it on every pull request against the merge base with main.
