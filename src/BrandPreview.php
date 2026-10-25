<?php

namespace Tey\Kit;

use Closure;
use Illuminate\Http\Request;

/**
 * The local-only brand switch: `?brand=<preset>` keeps one of `kit.brand.presets` for the session (`?brand=` goes back to the configured brand), so
 * two variants can be compared side by side on a running local site. Outside a local environment it does nothing at all.
 */
final class BrandPreview
{
    public function handle(Request $request, Closure $next): mixed
    {
        self::apply($request);

        return $next($request);
    }

    /** Applies the switch to this request (the session must have started). */
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

        // For the shell's environment pill, which names the preset in force (a request attribute, so no feature reaches into the brand).
        if (($active = Brand::activePreset()) !== null) {
            $request->attributes->set('kit.brand_preset', $active);
        }
    }
}
