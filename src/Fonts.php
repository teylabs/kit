<?php

namespace Tey\Kit;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use RuntimeException;

/**
 * `<x-kit::fonts />`, for the layout head: the font links of the set `kit.fonts` names (fonts.bunny.net), and a `:root`
 * block setting `--font-sans`, `--font-serif` (display), `--font-mono` and `--font-display-weight`, with system fallbacks.
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

    public function __construct(public ?string $nonce = null) {}

    public function render(): Htmlable
    {
        $set = self::set(config('kit.fonts'));
        // The facade, not a fresh container instance: a nonce the app set with Vite::useCspNonce() lives on the facade's Vite.
        $nonce = $this->nonce ?? rescue(fn () => Vite::cspNonce(), null, false);
        $attribute = is_string($nonce) && $nonce !== '' ? ' nonce="'.e($nonce).'"' : '';

        return new HtmlString(
            '<link rel="preconnect" href="https://fonts.bunny.net"'.$attribute.'>'."\n"
            .'<link rel="stylesheet" href="https://fonts.bunny.net/css?family='.$set['families'].'&display=swap"'.$attribute.'>'."\n"
            .'<style'.$attribute.'>:root { --font-sans: '.$set['sans'].'; --font-serif: '.$set['serif'].'; --font-mono: '.$set['mono'].'; --font-display-weight: '.$set['display_weight'].'; }</style>'
        );
    }
}
