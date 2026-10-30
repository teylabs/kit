# Kit

Guidance for contributors and coding agents working in this repository. `tey/kit` is a feature kit for tenant-aware Laravel apps, built for the Tey Labs fleet. Public but unsupported.

- Behaviour changes need a matching check. Identities (release and feature pins) are immutable: a changed resource byte means a re-pin, listed old to new in the PR body.
- Checks use synthetic fixtures only. No external services, production data or provider side effects.
- Fail closed. A refusal or incomplete run exits nonzero.
- State missing coverage (consumer, product, browser, database) explicitly rather than implying it.
- Every new page, layout or shell component needs a surfaces row (`resources/adoption/surfaces.json`) or a `resources/adoption/internal.json` entry; every `handRolled` signal needs a fixture under `tests/fixtures/adoption`. `composer test:adoption-completeness` enforces both (docs/adoption.md).

## Gates

```sh
composer update --prefer-dist --no-interaction
composer test:scaffold
composer test:features
composer test:catalog
composer test:lifecycle
composer test:resources
composer test:library
composer test:adopt
sh bin/privacy-check.sh
```

CI runs every `composer test:*` script on PHP 8.4 and 8.5, plus the Privacy job.

## Conventions

- Match the surrounding code: names, comment density, idiom. Class names stay `Blueprint`, `Catalog`, `Lifecycle` and so on; the namespace is `Tey\Kit`.
- Commit as Jasper Tey <jasper@teylabs.com>. Commits and PRs carry no attribution lines or trailers. Stage explicit paths only.
- Work on a branch and open a pull request. Never push to `main`, merge, tag or release.
- Never commit private project names, internal tooling references or local paths. `bin/privacy-check.sh` runs in CI.
