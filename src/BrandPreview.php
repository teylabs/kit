<?php

namespace Tey\Kit;

use Closure;
use Illuminate\Http\Request;
use Tey\Kit\Themes\Themes;

/**
 * The dev switches. `?theme=<preset>` keeps one of the theme presets (drafts included) for the session and `?theme=` clears it; it works only
 * where `kit.themes.switch_envs` allows (local and staging by default) and never in production, whatever the config says. `?brand=<preset>`
 * keeps one of `kit.brand.presets` for the session (`?brand=` goes back to the configured brand), only in a local environment. Elsewhere
 * both do nothing at all.
 */
final class BrandPreview
{
    public function handle(Request $request, Closure $next): mixed
    {
        self::apply($request);

        return $next($request);
    }

    /** Applies the switches to this request (the session must have started). */
    public static function apply(Request $request): void
    {
        if (app()->environment('local') && $request->hasSession() && $request->query->has('brand')) {
            $name = (string) $request->query('brand');
            if ($name !== '' && isset(Brand::presets()[$name])) {
                $request->session()->put('kit.brand_preset', $name);
            } else {
                $request->session()->forget('kit.brand_preset');
            }
        }

        if (Themes::switchEnabled() && $request->hasSession() && $request->query->has('theme')) {
            $name = $request->query('theme');
            if (is_string($name) && Themes::find($name) !== null) {
                $request->session()->put(Themes::SESSION_KEY, $name);
            } else {
                $request->session()->forget(Themes::SESSION_KEY);
            }
        }

        // For the shell's environment pill, which names the preset in force (a request attribute, so no feature reaches into the brand).
        if (($active = Brand::activePreset()) !== null) {
            $request->attributes->set('kit.brand_preset', $active);
        }
        if (($theme = Themes::switched()) !== null) {
            $request->attributes->set('kit.theme', $theme->name);
        }
    }
}
