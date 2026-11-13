<?php

// The kit's theme: kit.css defaults every semantic colour token (at the lowest precedence, light and dark), every token a kit stub colours with is one of
// them, the theming policy's advice (ThemeAdvisory::of), and the stylesheets kit:brand reads the theme from (ThemeAdvisory::themeStylesheets: app.css, the Vite
// CSS entries and their local imports, never outside the app). Synthetic stylesheets in a temp directory; no browser.
use Tey\Kit\ThemeAdvisory;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** The semantic colour tokens the kit's components use (shadcn-vue's names), which kit.css defines at the lowest precedence. */
const THEME_TOKENS = ['background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground', 'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'destructive', 'destructive-text', 'success-text', 'warning-text', 'info-text', 'border', 'input', 'ring'];

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$root = sys_get_temp_dir().'/kit-theme-'.bin2hex(random_bytes(6));
register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
try {
    // kit.css: every token at the lowest precedence, with a value in light and dark.
    $kitCss = (string) file_get_contents(dirname(__DIR__).'/resources/features/core/css/kit.css.stub');
    preg_match('/@theme default inline \{(.*?)\}/s', $kitCss, $defaults);
    // The colour blocks by content, not position: kit.css has other :where(:root) blocks (the stacking scale), in any order.
    preg_match('/:where\(:root\) \{([^}]*--background:[^}]*)\}/s', $kitCss, $light);
    preg_match('/:where\(\.dark\) \{([^}]*--background:[^}]*)\}/s', $kitCss, $dark);
    foreach (THEME_TOKENS as $token) {
        check(str_contains($defaults[1] ?? '', '--color-'.$token.': var(--'.$token.');') && preg_match('/--'.preg_quote($token, '/').':\s/', $light[1] ?? '') === 1 && preg_match('/--'.preg_quote($token, '/').':\s/', $dark[1] ?? '') === 1, 'kit.css maps --color-'.$token.' at default precedence, with zero-specificity light and dark values');
    }
    // Every semantic token a kit stub colours with is one kit.css defaults.
    $used = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/features', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (preg_match('/\.(vue|ts)\.stub$/', $file->getFilename())) {
            preg_match_all('/\b(?:bg|text|border|ring|outline|fill|stroke|divide|placeholder|from|to|via)-((?:background|foreground|card|popover|primary|secondary|muted|accent|destructive|success|warning|info|input|ring|border)(?:-foreground|-text)?)\b/', (string) file_get_contents($file->getPathname()), $classes);
            array_push($used, ...$classes[1]);
        }
    }
    $used = array_values(array_unique($used));
    check($used !== [] && array_diff($used, THEME_TOKENS) === [], 'every semantic token the kit\'s stubs use has a default in kit.css ('.count($used).' used)');

    // The theming policy: advice only.
    $advice = fn (string $css): array => ThemeAdvisory::of(['resources/css/app.css' => $css]);
    check($advice(":root { --card: oklch(0.99 0 0); --card-foreground: oklch(0.145 0 0); --shell-ground: #faf0e6; --foreground: #111111; }\n.dark { --card: oklch(0.205 0 0); --card-foreground: oklch(0.985 0 0); }\n[data-shell-bar] { --kit-mark: red; }\n") === [], 'surfaces set with their foregrounds, readable, and --kit-* only on a named surface: no advice');
    $unpaired = $advice(":root { --muted: #f4f4f5; }\n.dark { --popover: #18181b; }\n");
    check(count($unpaired) === 2 && str_contains($unpaired[0], ':root sets --muted without --muted-foreground') && str_contains($unpaired[1], '.dark sets --popover without --popover-foreground'), 'a surface without its foreground is advised, per scope');
    $low = $advice(":root { --background: #ffffff; --foreground: #aaaaaa; }\n.dark { --shell-ground: rgb(40, 40, 40); --foreground: oklch(0.3 0 0); }\n");
    check(count($low) === 2 && str_contains($low[0], ':root --foreground on --background is 2.32:1, below 4.5:1') && str_contains($low[1], '.dark --foreground on --shell-ground is'), 'a pair below 4.5:1 is advised, --shell-ground read against --foreground');
    $owned = $advice(":root { --kit-mark: red; }\n.brand-card { --kit-ring: blue; }\n");
    check(count($owned) === 2 && str_contains($owned[0], 'sets --kit-mark on :root: the kit owns its --kit-* tokens') && str_contains($owned[1], 'on .brand-card'), 'a --kit-* override off the named surfaces is advised');
    check($advice(":root { --background: var(--brand-ground); --foreground: #000000; }\n") === [], 'a colour the check cannot read is skipped, not guessed');

    // The stylesheets the theme is read from: app.css, each Vite CSS entry, and their local imports.
    mkdir($root.'/resources/css', 0700, true);
    $sheets = fn (): array => array_keys(ThemeAdvisory::themeStylesheets($root));
    check($sheets() === [], 'no stylesheet: none');
    file_put_contents($root.'/resources/css/app.css', "@import './theme.css';\n@import 'tailwindcss';\n:root { --primary: #123456; }\n");
    file_put_contents($root.'/resources/css/theme.css', "@import url('./app.css');\n:root { --background: #ffffff; }\n");
    check($sheets() === ['resources/css/app.css', 'resources/css/theme.css'], 'app.css and its local import are read; a package import is skipped, and an import cycle ends');
    file_put_contents($root.'/resources/css/admin.css', ":root { --primary: #abcdef; }\n");
    check(! in_array('resources/css/admin.css', $sheets(), true), 'a stylesheet no entry names is not read');
    file_put_contents($root.'/vite.config.ts', "laravel({ input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.ts'] })\n");
    check(in_array('resources/css/admin.css', $sheets(), true) && ! in_array('resources/js/app.ts', $sheets(), true), 'a second Vite CSS entry is read; a script entry is not');
    file_put_contents(dirname($root).'/outside-'.basename($root).'.css', ':root { --primary: #000000; }');
    file_put_contents($root.'/resources/css/admin.css', "@import '../../../outside-".basename($root).".css';\n");
    check(array_filter($sheets(), fn (string $path): bool => str_contains($path, 'outside')) === [], 'an import outside the app root is not followed');
    unlink(dirname($root).'/outside-'.basename($root).'.css');
    // A local import through a linked directory: the file's real path must be the path read, under the root, so no component of it is a link.
    $outside = dirname($root).'/outside-'.basename($root);
    mkdir($outside);
    file_put_contents($outside.'/theme.css', ':root { --primary: #000000; }');
    symlink($outside, $root.'/resources/css/vendor');
    mkdir($root.'/resources/css/real');
    file_put_contents($root.'/resources/css/real/theme.css', ':root { --ring: #000000; }');
    symlink($root.'/resources/css/real', $root.'/resources/css/inside');
    file_put_contents($root.'/resources/css/admin.css', "@import './vendor/theme.css';\n@import './inside/theme.css';\n@import './real/theme.css';\n");
    check(! in_array('resources/css/vendor/theme.css', $sheets(), true), 'an import through a linked directory outside the app root is not read');
    check(! in_array('resources/css/inside/theme.css', $sheets(), true) && in_array('resources/css/real/theme.css', $sheets(), true), 'nor one through a linked directory inside it; the same file by its real path is');
    // The app root itself may be a link (a `current` deploy): only the folders from the root down are checked.
    symlink($root, $root.'-current');
    check(in_array('resources/css/app.css', array_keys(ThemeAdvisory::themeStylesheets($root.'-current')), true) && in_array('resources/css/real/theme.css', array_keys(ThemeAdvisory::themeStylesheets($root.'-current')), true), 'a linked app root is read through');
    unlink($root.'-current');
    unlink($root.'/resources/css/vendor');
    exec('rm -rf '.escapeshellarg($outside));
    echo "Theme checks on kit.css, the kit's stubs and synthetic stylesheets; no browser or host app.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
