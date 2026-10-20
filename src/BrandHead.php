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

        return new HtmlString(implode("\n", $links));
    }
}
