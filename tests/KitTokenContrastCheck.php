<?php

// The kit's own default colour tokens (resources/features/core/css/kit.css.stub) meet WCAG 2.2 AA: each text token on the surface it is drawn on reaches 4.5:1,
// in the light defaults, the .kit-theme-light subtree and dark. A host's own tokens override these; this keeps the kit's defaults from regressing.
// Page grounds (README, "Tinted page grounds"): in every theme, shipped and the sample tinted one (tests/fixtures/tinted-ground-theme.css, ground #f3f5e7),
// light and dark, --foreground and --muted-foreground reach 4.5:1 on the ground (--shell-ground, else --background) and on the surface (--card). A theme
// that declares `--kit-ground: tinted` also needs its surface to stand off the ground: --card against the ground at least 1.1:1 (and the card has a --border
// edge), --border against --card at least 1.5:1, and a control's edge (--input) against its surface (--card) at least 3:1. The shipped themes are white
// grounds (no declaration), where the kit draws no ground card, so those three pairs are not theirs.

function check(bool $ok, string $message): void
{
    if (! $ok) {
        fwrite(STDERR, 'FAIL '.$message."\n");
        exit(1);
    }
    echo 'PASS '.$message."\n";
}

/** @return array{0: float, 1: float, 2: float} sRGB 0..1 from `hsl(h s% l%)` or `#rrggbb` */
function hslToRgb(string $value): array
{
    if (preg_match('/\A#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})\z/i', trim($value), $hex)) {
        return [hexdec($hex[1]) / 255, hexdec($hex[2]) / 255, hexdec($hex[3]) / 255];
    }
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
// Page grounds.
$tinted = file_get_contents(__DIR__.'/fixtures/tinted-ground-theme.css');
$tintedLight = [...$light, ...block($tinted, ':root')];
$grounds = [...$themes, 'tinted sample' => $tintedLight, 'tinted sample dark' => [...$themes['dark'], ...$tintedLight, ...block($tinted, '.dark')]];
check(! str_contains($css, '--kit-ground'), 'kit.css declares no ground: the default is white, where no ground card is drawn');
check(($tintedLight['kit-ground'] ?? null) === 'tinted', 'the sample theme declares --kit-ground: tinted');
check($tintedLight['background'] === '#f3f5e7', 'the sample tinted ground is #f3f5e7');
$surface = file_get_contents(__DIR__.'/../resources/features/core/components/Kit/Surface/surface.ts.stub');
check(preg_match("/export const groundCard =\s*'bg-card text-card-foreground rounded-xl border';/", $surface) === 1, 'the ground card is bg-card text-card-foreground rounded-xl border: a --border edge, as a dashboard widget');
foreach ($grounds as $theme => $tokens) {
    $ground = $tokens['shell-ground'] ?? $tokens['background'];
    foreach (['foreground', 'muted-foreground'] as $text) {
        foreach (['ground' => $ground, 'card' => $tokens['card']] as $name => $on) {
            $ratio = contrast($tokens[$text], $on);
            check($ratio >= 4.5, sprintf('%s: --%s on the %s is %.2f:1 (4.5:1)', $theme, $text, $name, $ratio));
        }
    }
    if (($tokens['kit-ground'] ?? null) !== 'tinted') {
        echo "SKIP {$theme}: a white ground (no --kit-ground: tinted), so no ground card and no surface pairs\n";

        continue;
    }
    foreach ([['card', $ground, 'the ground', 1.1], ['border', $tokens['card'], '--card', 1.5], ['input', $tokens['card'], '--card', 3.0]] as [$token, $on, $name, $minimum]) {
        $ratio = contrast($tokens[$token], $on);
        check($ratio >= $minimum, sprintf('%s: --%s against %s is %.2f:1 (%.1f:1)', $theme, $token, $name, $ratio, $minimum));
    }
}
echo "Token contrast is computed from the stub's hsl() and the sample's hex values (WCAG 2.x relative luminance); no browser.\n"
;
