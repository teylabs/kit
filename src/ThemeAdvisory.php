<?php

namespace Tey\Kit;

/**
 * The theming policy, read from the host's stylesheets as advice (never a failure). An app may remap the surface tokens in `:root` and `.dark`, each with its
 * foreground; the kit owns `--kit-*`, which an app sets only on the surfaces named here. Each finding is one plain sentence: a surface set without its
 * foreground, a pair below 4.5:1, or a `--kit-*` override elsewhere. Colours the check cannot read (a `var()`, a colour with alpha, a named colour) are skipped.
 */
final class ThemeAdvisory
{
    /** The surfaces an app may remap, each with the foreground it must set beside it (`shell-ground` is read against `foreground`, which it does not own). */
    public const PAIRS = ['background' => 'foreground', 'card' => 'card-foreground', 'popover' => 'popover-foreground', 'muted' => 'muted-foreground', 'sidebar' => 'sidebar-foreground', 'shell-ground' => null];

    /** The elements on which an app may set the kit's own `--kit-*` tokens. */
    public const KIT_SURFACES = ['[data-shell-bar]', '[data-shell-subbar]', '[data-navigation-header]'];

    /**
     * @param  array<string, string>  $stylesheets  contents by path relative to the host root (a kit.css copy is skipped: it is the kit's own)
     * @return list<string>
     */
    public static function of(array $stylesheets): array
    {
        $scopes = ['light' => [], 'dark' => []];
        $advice = [];
        foreach ($stylesheets as $path => $css) {
            if (basename($path) === 'kit.css') {
                continue;
            }
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
            preg_match_all('/([^{};]*)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);
            foreach ($blocks as [, $selector, $body]) {
                $selector = trim($selector);
                preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+)/i', $body, $declarations, PREG_SET_ORDER);
                foreach ($declarations as [, $name, $value]) {
                    if (str_starts_with($name, 'kit-') && ! in_array($selector, self::KIT_SURFACES, true)) {
                        $advice[] = $path.' sets --'.$name.' on '.($selector === '' ? 'the top level' : $selector).': the kit owns its --kit-* tokens. Set them only on '.implode(', ', self::KIT_SURFACES).'.';
                    }
                    $scope = preg_match('/(^|[\s,])\.dark\b/', $selector) === 1 ? 'dark' : (preg_match('/(^|[\s,]):root\b/', $selector) === 1 ? 'light' : null);
                    if ($scope !== null) {
                        $scopes[$scope][$name] = trim($value);
                    }
                }
            }
        }
        foreach ($scopes as $scope => $tokens) {
            $where = $scope === 'dark' ? '.dark' : ':root';
            foreach (self::PAIRS as $surface => $foreground) {
                if (! isset($tokens[$surface])) {
                    continue;
                }
                if ($foreground !== null && ! isset($tokens[$foreground])) {
                    $advice[] = $where.' sets --'.$surface.' without --'.$foreground.': set a surface and its foreground together.';

                    continue;
                }
                $text = $tokens[$foreground ?? 'foreground'] ?? null;
                [$back, $front] = [self::hex($tokens[$surface]), $text === null ? null : self::hex($text)];
                if ($back !== null && $front !== null && ($ratio = Brand::contrast($front, $back)) < 4.5) {
                    $advice[] = $where.' --'.($foreground ?? 'foreground').' on --'.$surface.' is '.round($ratio, 2).':1, below 4.5:1.';
                }
            }
        }

        return array_values(array_unique($advice));
    }

    /** A colour as #rrggbb: hex (3 or 6 digits), rgb() or oklch() without alpha; null for anything else. */
    public static function hex(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (preg_match('/\A#([0-9a-f]{3}|[0-9a-f]{6})\z/', $value, $m) === 1) {
            return '#'.(strlen($m[1]) === 3 ? implode('', array_map(fn (string $c): string => $c.$c, str_split($m[1]))) : $m[1]);
        }
        if (preg_match('/\Argba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)\s*\)\z/', $value, $m) === 1) {
            return self::rgb((int) $m[1] / 255, (int) $m[2] / 255, (int) $m[3] / 255);
        }
        if (preg_match('/\Aoklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?\s*\)\z/', $value, $m) === 1) {
            $l = (float) $m[1] / ($m[2] === '%' ? 100 : 1);
            [$a, $b] = [(float) $m[3] * cos(deg2rad((float) $m[4])), (float) $m[3] * sin(deg2rad((float) $m[4]))];
            [$l_, $m_, $s_] = [($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3, ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3, ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3];
            $gamma = fn (float $c): float => ($c = max(0.0, $c)) <= 0.0031308 ? 12.92 * $c : 1.055 * ($c ** (1 / 2.4)) - 0.055;

            return self::rgb($gamma(4.0767416621 * $l_ - 3.3077115913 * $m_ + 0.2309699292 * $s_), $gamma(-1.2684380046 * $l_ + 2.6097574011 * $m_ - 0.3413193965 * $s_), $gamma(-0.0041960863 * $l_ - 0.7034186147 * $m_ + 1.7076147010 * $s_));
        }

        return null;
    }

    private static function rgb(float $r, float $g, float $b): string
    {
        return '#'.implode('', array_map(fn (float $c): string => str_pad(dechex((int) round(max(0, min(1, $c)) * 255)), 2, '0', STR_PAD_LEFT), [$r, $g, $b]));
    }
}
