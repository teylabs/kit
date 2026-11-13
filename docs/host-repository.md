# The host's repository

What a kit host commits, and how to set up a fresh checkout.

## Lineage records

Since 0.18 the kit reads no lineage record. `.mod-maintenance/` (its lineage records, `installed.json`, journals, the lock and `owner.json`) and `.mod-maintenance-stage-*` belonged to the install and update tooling 0.18 removed, so a fresh clone or a CI run needs none of them. Once an app is on 0.18 (after `kit:detach` on 0.17.8 and the changelog's 0.18 "Upgrading" steps), it can delete them and drop their `.gitignore` lines.

## A fresh checkout or CI

In a fresh checkout of a kit host, lint and `vue-tsc` fail until the Wayfinder files exist, even on an unmodified main: the pages import generated routes and actions. Set up in this order:

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan wayfinder:generate   # or: npm ci && npm run build
```

Then run lint and the type check. In CI, put `wayfinder:generate` (or the build) before those steps.
