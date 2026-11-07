<?php

// The kit's own default colour tokens (resources/features/core/css/kit.css.stub) meet WCAG 2.2 AA: each text token on the surface it is drawn on reaches 4.5:1,
// in the light defaults, the .kit-theme-light subtree and dark. A host's own tokens override these; this keeps the kit's defaults from regressing.

function check(bool $ok, string $message): void
{
    if (! $ok) {
        fwrite(STDERR, 'FAIL '.$message."\n");
        exit(1);
    }
    echo 'PASS '.$message."\n";
}

/** @return array{0: float, 1: float, 2: float} sRGB 0..1 from `hsl(h s% l%)` */
function hslToRgb(string $value): array
{
    if (! preg_match('/\Ahsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)\z/', trim($value), $m)) {
        throw new RuntimeException('Not an hsl() token: '.$value);
    }
    [$h, $s, $l] = [(float) $m[1] / 360, (float) $m[2] / 100, (float) $m[3] / 100];
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $hue = function (float $t) use ($p, $q): float {
        $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);

        return $t < 1 / 6 ? $p + ($q - $p) * 6 * $t : ($t < 1 / 2 ? $q : ($t < 2 / 3 ? $p + ($q - $p) * (2 / 3 - $t) * 6 : $p));
    };

    return $s == 0.0 ? [$l, $l, $l] : [$hue($h + 1 / 3), $hue($h), $hue($h - 1 / 3)];
}

function contrast(string $a, string $b): float
{
    $luminance = function (string $colour): float {
        [$r, $g, $b] = array_map(fn (float $c): float => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, hslToRgb($colour));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };
    [$x, $y] = [$luminance($a), $luminance($b)];

    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

/** @return array<string, string> the custom properties declared in the first block whose selector is exactly `$selector` */
function block(string $css, string $selector): array
{
    if (! preg_match('/^'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m', $css, $m)) {
        throw new RuntimeException('No block for '.$selector);
    }
    preg_match_all('/--([a-z-]+):\s*([^;]+);/', $m[1], $pairs, PREG_SET_ORDER);

    return array_column($pairs, 2, 1);
}

$css = file_get_contents(__DIR__.'/../resources/features/core/css/kit.css.stub');
$light = block($css, ':where(:root)');
$themes = ['light' => $light, '.kit-theme-light' => [...$light, ...block($css, '.kit-theme-light')], 'dark' => [...$light, ...block($css, ':where(.dark)')]];
$pairs = [
    ['foreground', 'background'], ['card-foreground', 'card'], ['popover-foreground', 'popover'], ['primary-foreground', 'primary'],
    ['secondary-foreground', 'secondary'], ['accent-foreground', 'accent'], ['muted-foreground', 'muted'], ['muted-foreground', 'background'],
    ['destructive-foreground', 'destructive'], ['destructive-text', 'background'], ['success-text', 'background'], ['warning-text', 'background'], ['info-text', 'background'],
];
foreach ($themes as $theme => $tokens) {
    foreach ($pairs as [$text, $surface]) {
        $ratio = contrast($tokens[$text], $tokens[$surface]);
        check($ratio >= 4.5, sprintf('%s: --%s on --%s is %.2f:1 (AA 4.5:1)', $theme, $text, $surface, $ratio));
    }
}
echo "Token contrast is computed from the stub's hsl() values (WCAG 2.x relative luminance); no browser.\n";
