# Upgrading: the brand moves to config/brand.php

The brand's values move out of `.env` into the app's own committed `config/brand.php`. Same keys, same nesting as `kit.brand`; every reader (`config('kit.brand.*')`, `Brand`, `<x-kit::brand />`, `kit:brand`, mail, the shell) is unchanged. Nothing is removed or renamed in this release: the `KIT_BRAND_*` env values still work, deprecated, until 0.17.

## Why

An unquoted `#` in .env starts a comment, so `KIT_BRAND_ACCENT=#c2410c` loads as an empty value and the colour silently disappears. A PHP file has no such trap, is reviewed with the code, and is cached by `config:cache`.

## How each key resolves

At boot, for each key:

1. `config/brand.php`, when the key is present, an explicit `null` included;
2. else its `KIT_BRAND_*` env value (deprecated: one warning per key per process, naming the key and `kit:brand:migrate`);
3. else the kit's default.

`php artisan kit:status` lists each key with its source: `config`, `env (deprecated)` or `default`.

Colour keys take a `#rgb` or `#rrggbb`. `null` means "use the theme" (or the kit's default). An empty `KIT_BRAND_*` colour (often an unquoted `#hex` read as a comment) reads as `null`: the theme, with a hint logged once per key and `kit:status` showing `empty in env (theme)`. A config cache built by 0.15.49 or 0.15.50 keeps it at the kit default, listed as invalid, until you run `config:cache` (or `config:clear`) again. An empty string in config/brand.php, or any value that isn't a colour, is invalid: in local and testing the boot throws, naming the key; in any other environment it logs a warning and the key takes the kit's default.

## Steps

1. Run `php artisan kit:brand:migrate`. It writes `config/brand.php` with only the keys that come from env today, at the values as loaded. A key whose `.env` line loaded empty is written as `null` with a comment (`// KIT_BRAND_ACCENT loaded empty (unquoted #?). null = use theme; set a value to override.`), so the app keeps its current look; put the real colour there. It never guesses a value, never edits .env, and refuses to overwrite an existing `config/brand.php` without `--force`.
2. Fill in the flagged keys, commit `config/brand.php`.
3. Delete the .env lines it printed, and the same variables from your platform's environment.
4. `php artisan config:clear` (or `config:cache`) and check `php artisan kit:status`: no key should read `env (deprecated)`.

For a new app, `php artisan vendor:publish --tag=kit-brand` publishes the stub: every key commented out at the kit's default.
