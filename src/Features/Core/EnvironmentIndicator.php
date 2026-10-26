<?php

namespace Tey\Kit\Features\Core;

/**
 * The quiet sign that this is not production: `[Staging] ` before the document title and a 2px edge across the top of the shell, in the environment's own colour.
 * It is configured per environment in `kit.environment_indicator` (environment name => `label` and `colour`), carries no product name, and is never on in production,
 * whatever the config says. An entry that cannot be shown safely (an unreadable label, a colour that is not #rrggbb, or an edge below 3:1 on the light or the dark shell)
 * is off rather than approximate.
 */
final class EnvironmentIndicator
{
    /** The edge is a non-text component: WCAG 1.4.11 asks 3:1 against what it sits on. */
    public const MINIMUM_CONTRAST = 3.0;

    /** @return ?array{label: string, colour: string} the entry for this environment, or null when there is none to show */
    public static function current(): ?array
    {
        $environment = (string) app()->environment();
        if ($environment === 'production') {
            return null;
        }
        $entry = (array) config('kit.environment_indicator', []);
        $entry = is_array($entry[$environment] ?? null) ? $entry[$environment] : null;
        if ($entry === null) {
            return null;
        }
        $label = is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
        $colour = is_string($entry['colour'] ?? null) ? strtolower($entry['colour']) : '';

        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9 \-]{0,19}\z/', $label) === 1 && self::problems($colour) === [] ? ['label' => $label, 'colour' => $colour] : null;
    }

    /** The document title with the environment before it (`[Staging] Cases · Product`); unchanged when there is no indicator. */
    public static function title(string $title): string
    {
        $current = self::current();

        return $current === null ? $title : '['.$current['label'].'] '.$title;
    }

    /**
     * What stops a colour from being the edge: not a #rrggbb, or under 3:1 against the light shell (the brand's page colour, white unless set) or the dark one (`#09090b` unless set).
     *
     * @return list<string>
     */
    public static function problems(string $colour): array
    {
        if (preg_match('/\A#[0-9a-f]{6}\z/', $colour) !== 1) {
            return ['the colour is not a #rrggbb'];
        }
        // The brand's own page colours when it sets them (`kit.brand.background` and `background_dark`), the theme's neutral otherwise.
        $page = fn (string $key, string $default): string => is_string(config('kit.brand.'.$key)) && preg_match('/\A#[0-9a-fA-F]{6}\z/', config('kit.brand.'.$key)) === 1 ? strtolower(config('kit.brand.'.$key)) : $default;
        $problems = [];
        foreach (['the light shell' => $page('background', '#ffffff'), 'the dark shell' => $page('background_dark', '#09090b')] as $where => $background) {
            if (self::contrast($colour, $background) < self::MINIMUM_CONTRAST) {
                $problems[] = 'the edge is '.round(self::contrast($colour, $background), 2).':1 on '.$where.', below '.self::MINIMUM_CONTRAST.':1';
            }
        }

        return $problems;
    }

    /** The WCAG 2 contrast ratio of two #rrggbb colours (the kit's brand has the same, but a feature does not depend on it). */
    private static function contrast(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(fn (string $part): float => (static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4)(hexdec($part) / 255), str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };

        return (max($luminance($a), $luminance($b)) + 0.05) / (min($luminance($a), $luminance($b)) + 0.05);
    }
}
