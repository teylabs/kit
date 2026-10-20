<?php

namespace Tey\Kit;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * `<x-kit::brand />`, for the layout head: the favicon set `kit:brand` makes (SVG, the ICO with `sizes="32x32"` and the touch
 * icon), the theme colour and the social image. Never the framework's default favicon.
 */
final class BrandHead extends Component
{
    public function render(): HtmlString
    {
        $name = e(Brand::name());
        $accent = e(Brand::accent());
        $links = [
            '<link rel="icon" href="'.e(asset('favicon.svg')).'" type="image/svg+xml">',
            '<link rel="icon" href="'.e(asset('favicon.ico')).'" sizes="32x32">',
            '<link rel="apple-touch-icon" href="'.e(asset('apple-touch-icon.png')).'">',
            '<meta name="theme-color" content="'.$accent.'">',
            '<meta property="og:site_name" content="'.$name.'">',
            '<meta property="og:image" content="'.e(asset('og-image.png')).'">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
        ];

        // The colours in force: the configured brand, or the preset the local switch chose. A configured accent becomes the app's `brand` colour scale and the
        // semantic tokens (`--primary`, `--ring`, the nav marker); the neutral default leaves the theme's own zinc alone.
        $tokens = Brand::tokens(Brand::activePreset());
        $accent = e($tokens['accent']);
        $links[3] = '<meta name="theme-color" content="'.$accent.'">';
        if (Brand::css($tokens) !== '') {
            $links[] = '<style>'.Brand::css($tokens).'</style>';
        }
        if ($tokens['accent'] !== Brand::NEUTRAL_ACCENT) {
            $links[] = '<style>:root{--color-brand-50:color-mix(in oklab,'.$accent.' 8%,white);--color-brand-100:color-mix(in oklab,'.$accent.' 16%,white);--color-brand-200:color-mix(in oklab,'.$accent.' 30%,white);--color-brand-300:color-mix(in oklab,'.$accent.' 50%,white);--color-brand-400:color-mix(in oklab,'.$accent.' 75%,white);--color-brand-500:color-mix(in oklab,'.$accent.' 90%,white);--color-brand-600:'.$accent.';--color-brand-700:color-mix(in oklab,'.$accent.' 85%,black);--color-brand-800:color-mix(in oklab,'.$accent.' 70%,black);--color-brand-900:color-mix(in oklab,'.$accent.' 55%,black);--color-brand-950:color-mix(in oklab,'.$accent.' 40%,black)}</style>';
        }

        return new HtmlString(implode("\n", $links));
    }
}
