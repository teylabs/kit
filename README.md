# Starter blueprint delivery increment

Opinionated versioned application contract and public Mod scaffold pack. A/B and C/D are local synthetic release fixtures, not published versions. Runtime tenancy/membership/settings stay in maintained packages and host bindings.

The `starter-foundation` scaffold uses public released Mod APIs. Preview with `starter:foundation --dry-run --json`; then generate explicitly with `--no-interaction`. A/B generate one policy file. C/D add a separately owned defaults file for the two-file upgrade proof. It does not establish a bootable/authenticated application, persisted ownership, upgrade/recovery, browser or product-MCP acceptance.

Generated paths and host-owned branding/domain/configuration/data are distinct in the blueprint. The source reference content-addresses the manifest plus all resource bytes; baseline resources remain available for comparison. Generic lifecycle machinery is a separate reviewed Mod requirement. No private Mod APIs, forced overwrites, semantic merging, package publication or automatic production updates.

The initial recipe fixes the member name and ungrouped placement. Alternate command names cannot widen ownership. Only the Laravel layout with all declared paths and classes is supported; incompatible layouts refuse before generation.

The original A/B resource bytes and source references are pinned and retained. New C/D fixtures represent logical consumer baseline/target versions 0.3.0/0.4.0; both policy and defaults resources change. Each member has a fixed class/path, and every member validates the entire placement set before generation starts. Defaults occupy their own explicit folder so released Mod reverse-mapping remains unambiguous. Ordinary generation preserves existing customised output; it is not the persistent upgrade protocol.

Run `php tests/ScaffoldCheck.php <autoload.php> C` (also A/B/D) against a public Laravel13/Mod0.7.2 install. The optional `misplace-defaults` argument checks that a mismatched second member refuses before writing the first. Each run creates a distinct synthetic filesystem fixture, not a real application consumer or an installed ownership record.

18 October 2026: Added immutable two-file C/D proof resources and whole-recipe placement validation; initial A/B identities retained.
