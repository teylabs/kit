# Starter blueprint delivery increment

Opinionated versioned application contract and public Mod scaffold pack. A and B are local synthetic release fixtures, not published versions. Runtime tenancy/membership/settings stay in maintained packages and host bindings.

The `starter-foundation` scaffold uses public released Mod APIs. Preview with `starter:foundation --dry-run --json`; then generate explicitly with `--no-interaction`. It currently generates one policy file only. It does not establish a bootable/authenticated application, persisted ownership, upgrade/recovery, browser or product-MCP acceptance.

Generated paths and host-owned branding/domain/configuration/data are distinct in the blueprint. The source reference content-addresses the manifest plus all resource bytes; baseline resources remain available for comparison. Generic lifecycle machinery is a separate reviewed Mod requirement. No private Mod APIs, forced overwrites, semantic merging, package publication or automatic production updates.
