<?php

namespace Tey\Kit;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use RuntimeException;

/**
 * `<x-kit::fonts />`, for the layout head: the faces of the set `kit.fonts` names, and a `:root` block setting `--font-sans`, `--font-serif`
 * (display), `--font-mono` and `--font-display-weight`, with system fallbacks. `kit.fonts_source` says where the faces come from: `self` (the default),
 * `@font-face` rules for the files the kit ships (resources/fonts, published to public/vendor/kit/fonts with
 * `vendor:publish --tag=kit-fonts`), served from the app's own origin, so pages work offline and can read their own stylesheets' rules; or `cdn`,
 * fonts.bunny.net links. Self-hosted files that have not been published throw in local and testing; elsewhere the page falls back to the system fonts,
 * never to the CDN (README, "Self-hosted fonts").
 * Pages and the Tailwind theme use only those variables. An unknown set name is refused. Under a Content Security Policy every tag carries a nonce:
 * `<x-kit::fonts nonce="…" />`, else the app's `Vite::cspNonce()` when it set one; with neither, no nonce attribute is written.
 */
final class Fonts extends Component
{
    private const SANS = 'ui-sans-serif, system-ui, sans-serif';

    private const SERIF = 'ui-serif, Georgia, serif';

    private const MONO = 'ui-monospace, SFMono-Regular, Menlo, monospace';

    /** Set name => fonts.bunny.net families, then the sans, display and mono stacks and the display weight. */
    public const SETS = [
        'instrument' => [
            'families' => 'instrument-sans:400,500,600|instrument-serif:400,400i|ibm-plex-mono:400,500',
            'sans' => "'Instrument Sans', ".self::SANS,
            'serif' => "'Instrument Serif', ".self::SERIF,
            'mono' => "'IBM Plex Mono', ".self::MONO,
            'display_weight' => 400,
        ],
        'instrument-inter' => [
            'families' => 'inter:400,500,600|instrument-sans:600|ibm-plex-mono:400,500',
            'sans' => "'Inter', ".self::SANS,
            'serif' => "'Instrument Sans', ".self::SANS,
            'mono' => "'IBM Plex Mono', ".self::MONO,
            'display_weight' => 600,
        ],
        'inter-serif' => [
            'families' => 'inter:400,500,600|instrument-serif:400,400i|ibm-plex-mono:400,500',
            'sans' => "'Inter', ".self::SANS,
            'serif' => "'Instrument Serif', ".self::SERIF,
            'mono' => "'IBM Plex Mono', ".self::MONO,
            'display_weight' => 400,
        ],
        'hanken' => [
            'families' => 'hanken-grotesk:400,500,600|dm-serif-display:400|dm-mono:400,500',
            'sans' => "'Hanken Grotesk', ".self::SANS,
            'serif' => "'DM Serif Display', ".self::SERIF,
            'mono' => "'DM Mono', ".self::MONO,
            'display_weight' => 400,
        ],
    ];

    /** @return array{families: string, sans: string, serif: string, mono: string, display_weight: int} */
    public static function set(mixed $name): array
    {
        if (! is_string($name) || ! isset(self::SETS[$name])) {
            throw new RuntimeException('Unknown font set: '.(is_string($name) ? $name : get_debug_type($name)).'. Set KIT_FONTS (kit.fonts) to one of: '.implode(', ', array_keys(self::SETS)).'.');
        }

        return self::SETS[$name];
    }

    /** Where the self-hosted files are published, under public/. */
    public const PUBLISHED = 'vendor/kit/fonts';

    public function __construct(public ?string $nonce = null) {}

    /** The configured source, `cdn` or `self`; anything else is refused. */
    public static function source(): string
    {
        $source = config('kit.fonts_source', 'self');
        if ($source !== 'cdn' && $source !== 'self') {
            throw new RuntimeException('Unknown font source: '.(is_string($source) ? $source : get_debug_type($source)).'. Set KIT_FONTS_SOURCE (kit.fonts_source) to cdn or self.');
        }

        return $source;
    }

    /**
     * The shipped faces a set uses, one per family, weight, style and subset (resources/fonts/faces.json, written by bin/fonts-fetch.php).
     *
     * @param  array{families: string}  $set
     * @return list<array{family: string, slug: string, style: string, weight: int, subset: string, unicodeRange: string, file: string}>
     */
    public static function faces(array $set): array
    {
        /** @var array{faces: list<array{family: string, slug: string, style: string, weight: int, subset: string, unicodeRange: string, file: string}>} $shipped */
        $shipped = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/fonts/faces.json'), true, 512, JSON_THROW_ON_ERROR);
        $wanted = [];
        foreach (explode('|', $set['families']) as $family) {
            [$slug, $variants] = explode(':', $family);
            foreach (explode(',', $variants) as $variant) {
                $wanted[$slug.'|'.(int) $variant.'|'.(str_ends_with($variant, 'i') ? 'italic' : 'normal')] = true;
            }
        }

        return array_values(array_filter($shipped['faces'], fn (array $face): bool => isset($wanted[$face['slug'].'|'.$face['weight'].'|'.$face['style']])));
    }

    /** Whether the set's self-hosted files are all published to public/vendor/kit/fonts. */
    public static function published(array $faces): bool
    {
        foreach ($faces as $face) {
            if (! is_file(public_path(self::PUBLISHED.'/'.$face['file']))) {
                return false;
            }
        }

        return true;
    }

    public function render(): Htmlable
    {
        $set = self::set(config('kit.fonts'));
        // The facade, not a fresh container instance: a nonce the app set with Vite::useCspNonce() lives on the facade's Vite.
        $nonce = $this->nonce ?? rescue(fn () => Vite::cspNonce(), null, false);
        $attribute = is_string($nonce) && $nonce !== '' ? ' nonce="'.e($nonce).'"' : '';

        $root = ':root { --font-sans: '.$set['sans'].'; --font-serif: '.$set['serif'].'; --font-mono: '.$set['mono'].'; --font-display-weight: '.$set['display_weight'].'; }';
        if (self::source() === 'cdn') {
            return new HtmlString(
                '<link rel="preconnect" href="https://fonts.bunny.net"'.$attribute.'>'."\n"
                .'<link rel="stylesheet" href="https://fonts.bunny.net/css?family='.$set['families'].'&display=swap"'.$attribute.'>'."\n"
                .'<style'.$attribute.'>'.$root.'</style>'
            );
        }

        $faces = self::faces($set);
        if (! self::published($faces)) {
            if (app()->environment('local', 'testing')) {
                throw new RuntimeException('The self-hosted fonts are not published: run `php artisan vendor:publish --tag=kit-fonts --force` (kit.fonts_source is self).');
            }

            return new HtmlString('<style'.$attribute.'>'.$root.'</style>');
        }
        $rules = array_map(fn (array $face): string => "@font-face { font-family: '".$face['family']."'; font-style: ".$face['style'].'; font-weight: '.$face['weight']
            ."; font-display: swap; src: url('".e(asset(self::PUBLISHED.'/'.$face['file']))."') format('woff2'); unicode-range: ".$face['unicodeRange'].'; }', $faces);

        return new HtmlString('<style'.$attribute.'>'.implode("\n", $rules)."\n".$root.'</style>');
    }
}
