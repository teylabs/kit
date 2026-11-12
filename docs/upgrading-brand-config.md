# Upgrading: the brand moves to config/brand.php

The brand's values move out of `.env` into the app's own committed `config/brand.php`. Same keys, same nesting as `kit.brand`; every reader (`config('kit.brand.*')`, `Brand`, `<x-kit::brand />`, `kit:brand`, mail, the shell) is unchanged. Since 0.17, `config/brand.php` is the only source: the kit reads no `KIT_BRAND_*` env value. One still set is ignored, logged once at boot, and named by `kit:status` (`brand_env_ignored` in `--json`). From 0.15.49 to 0.16.x the env values still worked, deprecated.

## Why

An unquoted `#` in .env starts a comment, so `KIT_BRAND_ACCENT=#c2410c` loads as an empty value and the colour silently disappears. A PHP file has no such trap, is reviewed with the code, and is cached by `config:cache`.

## How each key resolves

At boot, for each key:

1. `config/brand.php`, when the key is present, an explicit `null` included;
2. else the kit's default.

`php artisan kit:status` lists each key with its source: `config` or `default`. A `KIT_BRAND_*` variable still set is not a source (0.17): it is ignored and listed under ignored variables.

Colour keys take a `#rgb` or `#rrggbb`. `null` means "use the theme" (or the kit's default). An empty string in config/brand.php, or any value that isn't a colour, is invalid: in local and testing the boot throws, naming the key; in any other environment it logs a warning and the key takes the kit's default.

## Steps

1. Run `php artisan kit:brand:migrate` before `config:cache`. It reads the process environment and `.env`, and writes `config/brand.php` with every `KIT_BRAND_*` value still set, cast as the kit cast it before 0.17. A key whose `.env` line loaded empty is written as `null` with a comment (`// KIT_BRAND_ACCENT loaded empty (unquoted #?). null = use theme; set a value to override.`), so the app keeps its current look; put the real colour there. It never guesses a value, never edits .env, and refuses to overwrite an existing `config/brand.php` without `--force`.
2. Fill in the flagged keys, commit `config/brand.php`.
3. Delete the .env lines it printed, and the same variables from your platform's environment. Grep `.github`, `.env.example`, `bin/` and `tests` for `KIT_BRAND_` too: a CI job or test that sets one now changes nothing.
4. `php artisan config:clear` (or `config:cache`) and check `php artisan kit:status`: no `KIT_BRAND_*` variable should be listed as ignored, and no 0.17 attention step should name a file.

For a new app, `php artisan vendor:publish --tag=kit-brand` publishes the stub: every key commented out at the kit's default.
