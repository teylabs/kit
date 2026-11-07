# Theme presets

A theme preset is a named look: the `kit.brand` colour tokens for light and dark mode, the heading, body and display fonts, and a corner radius. An app picks one with a single line, `KIT_THEME=community-paper`, and the shell, the brand CSS and the fonts follow. No rebuild is needed, because everything is set through CSS custom properties.

## What ships

| Preset | Status | Headings / body | Notes |
|---|---|---|---|
| `appraisal` | stable | Instrument Sans 600 / Inter | ink buttons and header, gold highlight and rule, rust link |
| `soft-studio` | stable | Fraunces 600 / Source Sans 3 | terracotta on warm paper, orange rule |
| `market-grotesk` | stable | Bricolage Grotesque 700 | rust on oat paper |
| `sunny-commons` | stable | Nunito 800 / Figtree | ink buttons with sunflower text, orange rule |
| `community-paper` | stable | Young Serif / Inter | deep green on newsprint, 4px corners |
| `funky-retro` | stable | DM Sans 600, Shrikhand mark | plum buttons and header, orange highlight |
| `retro-sunburst`, `retro-marquee`, `retro-cooper`, `retro-groovy`, `retro-evening`, `retro-sunny` | draft | Funky Retro round 2 | for the dev switch only |

`php artisan kit:theme` lists them. The light values come from the design picker. The dark values are a first pass that meets 4.5:1 and is still to be reviewed.

Where the picker could not be matched exactly:
- The kit's dark ("ink") header marks the active item with the accent. On the olive and near-black navs of Market Grotesk and Community Paper that is under 4.5:1, so those presets use the theme's own header with the coloured rule until there is a separate header-highlight token. The same applies to the coloured navs of the round-2 drafts and to Sunny Commons' yellow nav.
- Market Grotesk's button is `#b44f2a`, not `#c2562f`, because white text on `#c2562f` is under 4.5:1. Marquee Teal and Cooper Kitchen are darkened in the same way.
- The picker's muted text colour has no `kit.brand` token yet.

## Choosing the preset

The preset in force is resolved in this order:

1. the dev switch's session choice (`?theme=`), where the switch is allowed;
2. `kit.themes.default` (`KIT_THEME`);
3. none: the plain `kit.brand` values, byte for byte as before.

A per-workspace choice comes later, behind the `workspace.theme` capability. An unknown `KIT_THEME` is refused with the list of names rather than silently ignored.

A preset replaces the brand's colour keys as a whole: every key in `Preset::KEYS` is set, and a key the preset leaves out falls back to the kit default, not to `KIT_BRAND_*`. A `?brand=` preset still layers over it in local environments.

## Config

```php
'themes' => [
    'default' => env('KIT_THEME'),        // the app's preset, or null
    'offered' => null,                     // presets tenants may pick; null means every stable one, and never a draft
    'switch_envs' => ['local', 'staging'], // where ?theme= works; never production
    'paths' => [],                         // directories of the app's own preset files
],
```

An app whose published `config/kit.php` predates this block behaves as if it were unset.

## The dev switch

`?theme=<name>` keeps that preset, drafts included, for the session. `?theme=` with no value, or an unknown name, clears it. The switch works only in `switch_envs` and is hard-disabled in production whatever the config says. There, a stored choice is neither read nor changed. The request carries the switched name as the `kit.theme` attribute.

## Fonts

`<x-kit::fonts />` loads the active preset's heading, body and display families from fonts.bunny.net, sets the body as `--font-sans` and the heading as `--font-serif` (the display face) at the preset's heading weight, and keeps the configured set's mono. The design picker's Google Fonts URL is never sent to browsers.

A preset's `display` face (Funky Retro's Shrikhand) is the workspace-name mark: `--font-mark`, applied to the shell's workspace name and product name (`[data-shell-workspace]`, `[data-shell-product-name]`).

With `KIT_FONTS_SOURCE=self`, the preset's families are named, but their files are not shipped yet (that comes in a later release), so they fall back to the system stack. Self-hosting never falls back to a CDN.

`radius` and the variable-font axes are recorded on each preset. A later release prints `--radius` and ships the self-hosted faces.

## Mail and OG images

It is decided that a workspace's theme will reach its mail and OG images. `Brand::tokens()` already includes the preset in force, so `Brand::mail()` picks up `KIT_THEME` today. The per-workspace choice will be passed into the same token API when it lands.

## Adding a preset

1. Add `resources/themes/<name>.php` (or a file in one of your `kit.themes.paths`) returning `label`, `status` (`stable` or `draft`), `fonts` (`heading`, `body`, optional `display`, `weights`, `axes`), `radius`, `light` (the `kit.brand` keys) and `dark` (`accent`, `accent_foreground`, `button`, `background`, `card`, `border`, `muted`, `link`, mapped to their `_dark` keys).
2. Run `composer test:themes`: every preset must pass `Brand::contrastProblems()`.
3. Try it with `?theme=<name>`, then add a CHANGELOG line.
