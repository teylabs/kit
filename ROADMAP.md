# Roadmap

tey/kit adds tenant-aware features to a Laravel app you already have: workspaces and membership, access and grants, settings, session pages, MCP/OAuth, and a worked Notes example.

```sh
composer require tey/kit
php artisan kit:add workspaces settings
```

Each feature declares the files it owns, what it requires and what it conflicts with. `kit:add` shows a plan before it writes anything, claims files that already match, and refuses to overwrite your edits. `kit:update` upgrades what you added the same way.

1.0 means the feature catalog, the manifest format and the add/update contract are documented and frozen.

**Versions are milestones, not dates.**

## Shipped

- [x] **Blueprint groundwork.** Feature manifests with content-addressed identities, first adoption from an empty original, additive upgrades and external catalogs. Carried over from this package's predecessor; untagged here.
- [x] **[v0.1 — Add features to an existing app](https://github.com/teylabs/kit/milestone/1).** One lineage per feature with claiming of identical existing files, `kit:add`, `kit:status`, `kit:update` and `kit:list`, the starter's features as the catalog, and publication on Packagist.

## The countdown to 1.0

- [ ] **[v0.2 — Proven in a mature production app](https://github.com/teylabs/kit/milestone/2).** Adoption into an existing, independently developed app, and whatever that teaches.
- [ ] **[v0.3 — Friendlier plans](https://github.com/teylabs/kit/milestone/3).** Host steps, npm requirements, per-feature migrations and optional integrations, all spelled out in the plan.
- [ ] **[1.0 — Stable](https://github.com/teylabs/kit/milestone/4).** Frozen catalog format, add/update contract and support policy.
- [ ] **[1.x](https://github.com/teylabs/kit/milestone/5).** After 1.0, in no particular order.
