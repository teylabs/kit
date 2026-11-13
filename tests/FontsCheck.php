<?php

// The font sets and <x-kit::fonts />, rendered by Blade on a synthetic app; no network, browser or Tailwind build.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Fonts;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** Each set's links and variables, as the issue names them. */
const EXPECTED = [
    'instrument' => [
        'family=instrument-sans:400,500,600|instrument-serif:400,400i|ibm-plex-mono:400,500&display=swap',
        "--font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;",
        "--font-serif: 'Instrument Serif', ui-serif, Georgia, serif;",
        "--font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;",
        '--font-display-weight: 400;',
    ],
    'instrument-inter' => [
        'family=inter:400,500,600|instrument-sans:600|ibm-plex-mono:400,500&display=swap',
        "--font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;",
        "--font-serif: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;",
        "--font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;",
        '--font-display-weight: 600;',
    ],
    'inter-serif' => [
        'family=inter:400,500,600|instrument-serif:400,400i|ibm-plex-mono:400,500&display=swap',
        "--font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;",
        "--font-serif: 'Instrument Serif', ui-serif, Georgia, serif;",
        "--font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;",
        '--font-display-weight: 400;',
    ],
    'hanken' => [
        'family=hanken-grotesk:400,500,600|dm-serif-display:400|dm-mono:400,500&display=swap',
        "--font-sans: 'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif;",
        "--font-serif: 'DM Serif Display', ui-serif, Georgia, serif;",
        "--font-mono: 'DM Mono', ui-monospace, SFMono-Regular, Menlo, monospace;",
        '--font-display-weight: 400;',
    ],
];
/** Tailwind font-weight utilities; every other `font-*` class names a family, and must be one of the three variables. */
const WEIGHTS = ['thin', 'extralight', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold', 'black'];

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function refuses(Closure $operation, string $contains, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $contains), $message.' ('.$error->getMessage().')');

        return;
    }
    throw new RuntimeException('Expected refusal: '.$message);
}

try {
    $root = sys_get_temp_dir().'/kit-fonts-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();

    check(config('kit.fonts') === 'instrument', 'kit.fonts defaults to instrument');
    check(array_keys(Fonts::SETS) === ['instrument', 'instrument-inter', 'inter-serif', 'hanken'], 'there are exactly four sets');
    foreach (EXPECTED as $set => $expected) {
        config(['kit.fonts' => $set]);
        $html = Blade::render('<x-kit::fonts />');
        check(str_contains($html, '<link rel="preconnect" href="https://fonts.bunny.net">') && str_contains($html, '<link rel="stylesheet" href="https://fonts.bunny.net/css?'.$expected[0].'">'), $set.': the component links its fonts on fonts.bunny.net');
        check(str_contains($html, '<style>:root { ') && array_filter(array_slice($expected, 1), fn (string $variable): bool => ! str_contains($html, $variable)) === [], $set.': the component sets --font-sans, --font-serif, --font-mono and --font-display-weight with system fallbacks');
    }
    foreach (['nope', '', null] as $name) {
        config(['kit.fonts' => $name]);
        refuses(fn () => Blade::render('<x-kit::fonts />'), 'Unknown font set', 'an unknown set name ('.var_export($name, true).') is refused');
    }
    config(['kit.fonts' => 'nope']);
    refuses(fn () => Blade::render('<x-kit::fonts />'), 'one of: instrument, instrument-inter, inter-serif, hanken', 'the refusal names the four sets');

    // Pages use only the theme's variables: no font-family but font-sans, font-serif, font-mono and font-display, and no inline families.
    $tokens = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (preg_match('/\.(vue|vue\.stub|blade\.php)$/', $file->getFilename())) {
            $source = file_get_contents($file->getPathname());
            check(! preg_match('/font-family|fontFamily|font-\[/', $source), 'no inline or arbitrary font family in '.substr($file->getPathname(), strlen(dirname(__DIR__)) + 1));
            preg_match_all('/(?<![\w-])font-([a-z]+)(?![\w-])/', $source, $matches);
            $tokens = array_merge($tokens, $matches[1]);
        }
    }
    check(array_diff($tokens, WEIGHTS, ['sans', 'serif', 'mono', 'display']) === [], 'pages name no font family but font-sans, font-serif, font-mono and font-display (the kit\'s heading face, the serif unless a host sets --font-display)');
    // A Content Security Policy nonce on every tag: an explicit attribute, else Vite's; with neither, no attribute (the output above is unchanged).
    config(['kit.fonts' => 'instrument']);
    $plain = Blade::render('<x-kit::fonts />');
    check(! str_contains($plain, 'nonce='), 'without a nonce, no nonce attribute is written');
    $explicit = Blade::render('<x-kit::fonts nonce="abc123" />');
    check(substr_count($explicit, ' nonce="abc123"') === 3 && str_contains($explicit, '<link rel="preconnect" href="https://fonts.bunny.net" nonce="abc123">') && str_contains($explicit, '<style nonce="abc123">'), 'an explicit nonce is on both links and the style tag');
    Illuminate\Support\Facades\Vite::useCspNonce('vite-nonce');
    $vite = Blade::render('<x-kit::fonts />');
    check(substr_count($vite, ' nonce="vite-nonce"') === 3, 'without an attribute, Vite::cspNonce() is used on every tag');
    check(substr_count(Blade::render('<x-kit::fonts nonce="mine" />'), ' nonce="mine"') === 3, 'an explicit nonce wins over Vite\'s');
    check(str_contains(Blade::render('<x-kit::fonts nonce=\'a"b\' />'), ' nonce="a&quot;b"'), 'the nonce is escaped');

    // Self-hosted (kit.fonts_source = self): the shipped files, every one a real woff2 under an OFL license, cover every set's families, weights and styles.
    $app->forgetInstance(Vite::class);
    Illuminate\Support\Facades\Vite::clearResolvedInstance(Vite::class);
    check(config('kit.fonts_source') === 'cdn', 'kit.fonts_source defaults to cdn: the output above is unchanged');
    $shipped = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/fonts/faces.json'), true);
    foreach ($shipped['faces'] as $face) {
        $bytes = (string) @file_get_contents(dirname(__DIR__).'/resources/fonts/files/'.$face['file']);
        if (! str_starts_with($bytes, 'wOF2')) {
            throw new RuntimeException('not a woff2 file: '.$face['file']);
        }
    }
    check(count($shipped['faces']) > 0, 'every shipped face is a woff2 file in resources/fonts/files ('.count($shipped['faces']).' faces)');
    foreach (array_unique(array_column($shipped['faces'], 'slug')) as $slug) {
        $license = dirname(__DIR__).'/resources/fonts/licenses/'.$slug.'.txt';
        check(is_file($license) && str_contains((string) file_get_contents($license), 'SIL Open Font License'), $slug.' ships its SIL Open Font License');
    }
    foreach (Fonts::SETS as $name => $set) {
        foreach (explode('|', $set['families']) as $family) {
            [$slug, $variants] = explode(':', $family);
            foreach (explode(',', $variants) as $variant) {
                $style = str_ends_with($variant, 'i') ? 'italic' : 'normal';
                $found = array_filter($shipped['faces'], fn (array $face): bool => $face['slug'] === $slug && $face['weight'] === (int) $variant && $face['style'] === $style && $face['subset'] === 'latin');
                if ($found === []) {
                    throw new RuntimeException("$name: no shipped latin face for $slug $variant; run bin/fonts-fetch.php");
                }
            }
        }
    }
    check(true, 'every set\'s family, weight and style has a shipped latin face (bin/fonts-fetch.php keeps them in step with Fonts::SETS)');

    config(['kit.fonts_source' => 'self', 'kit.fonts' => 'instrument']);
    $app->instance('env', 'testing');
    refuses(fn () => Blade::render('<x-kit::fonts />'), 'vendor:publish --tag=kit-fonts', 'in testing, self-hosted fonts that are not published are refused, naming the publish command');
    check(app('kit.fonts-unpublished')() === true, 'kit:preflight\'s probe reports self-hosted fonts that are not published');
    $app->instance('env', 'production');
    $fallback = Blade::render('<x-kit::fonts />');
    check(! str_contains($fallback, 'bunny.net') && ! str_contains($fallback, '@font-face') && str_contains($fallback, "--font-sans: 'Instrument Sans', ui-sans-serif"), 'in production, unpublished self-hosted fonts fall back to the system fonts, never the CDN');
    $app->instance('env', 'testing');
    Artisan::call('vendor:publish', ['--tag' => 'kit-fonts', '--force' => true]);
    check(app('kit.fonts-unpublished')() === false, 'once published, the preflight probe is quiet');
    check(is_dir(public_path(Fonts::PUBLISHED)) && count(glob(public_path(Fonts::PUBLISHED.'/*.woff2'))) === count($shipped['faces']), 'vendor:publish --tag=kit-fonts copies every file to public/vendor/kit/fonts');
    foreach (array_keys(EXPECTED) as $name) {
        config(['kit.fonts' => $name]);
        $html = Blade::render('<x-kit::fonts />');
        $faces = Fonts::faces(Fonts::SETS[$name]);
        preg_match_all("/src: url\('([^']+)'\)/", $html, $sources);
        check(! str_contains($html, 'bunny.net') && ! str_contains($html, 'jsdelivr') && substr_count($html, '@font-face') === count($faces) && $sources[1] !== []
            && array_filter($sources[1], fn (string $url): bool => ! str_starts_with($url, asset(Fonts::PUBLISHED.'/'))) === [], $name.': self-hosted, one @font-face per shipped face, every source under the app\'s own asset() root, no CDN');
        check(array_filter(array_slice(EXPECTED[$name], 1), fn (string $variable): bool => ! str_contains($html, $variable)) === [], $name.': self-hosted sets the same variables');
    }
    config(['kit.fonts' => 'instrument-inter']);
    $inter = Blade::render('<x-kit::fonts />');
    check(! str_contains($inter, "font-family: 'Instrument Sans'; font-style: normal; font-weight: 400;") && str_contains($inter, "font-family: 'Instrument Sans'; font-style: normal; font-weight: 600;"), 'only the weights the set names: instrument-inter carries Instrument Sans 600, not 400');
    check(substr_count(Blade::render('<x-kit::fonts nonce="n1" />'), ' nonce="n1"') === 1, 'self-hosted is one <style> tag, carrying the nonce');
    config(['kit.fonts_source' => 'google']);
    refuses(fn () => Blade::render('<x-kit::fonts />'), 'Unknown font source', 'an unknown font source is refused');
    config(['kit.fonts_source' => 'cdn']);
    check(app('kit.fonts-unpublished')() === false, 'with the CDN source, the preflight probe is quiet');

    echo "Font checks render the component only; no network, browser or Tailwind build.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
