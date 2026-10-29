# The host's repository

What a kit host commits, and how to set up a fresh checkout.

## Lineage records

Each adopted feature has an installed lineage record at `.mod-maintenance/installed/<hash>.json` (and older installs have `.mod-maintenance/installed.json`). The record embeds every file the lineage owns, base64-encoded, with the artifact's identity and metadata. That's why it's large: a core record can be around 750K.

The bodies are there so the kit can work offline. A plan diffs the app's files against the bytes it installed, and a re-apply needs those bytes, without fetching the old release.

Commit the records. They're portable since tey/mod 0.11.3, and without them a fresh clone or a CI run loses the adoption.

Keep the local parts ignored:

- `journal-*.json`, the recovery journal of an apply in progress;
- `lock`, the maintenance lock;
- `owner.json`, which records this checkout's absolute root;
- `.mod-maintenance-stage-*`, a short-lived staging directory.

The recommended `.gitignore` lines:

```gitignore
/.mod-maintenance/journal-*.json
/.mod-maintenance/lock
/.mod-maintenance/owner.json
/.mod-maintenance-stage-*
```

If your `.gitignore` has `/.mod-maintenance`, replace it with these lines, then commit `.mod-maintenance/installed/`.

## A fresh checkout or CI

In a fresh checkout of a kit host, lint and `vue-tsc` fail until the Wayfinder files exist, even on an unmodified main: the pages import generated routes and actions. Set up in this order:

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan wayfinder:generate   # or: npm ci && npm run build
```

Then run lint and the type check. In CI, put `wayfinder:generate` (or the build) before those steps.
