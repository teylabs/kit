<?php

// The font sets and <x-kit::fonts />, rendered by Blade on a synthetic app; no network, browser or Tailwind build.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
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

    // Pages use only the three variables: no font-family but font-sans, font-serif and font-mono, and no inline families.
    $tokens = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (preg_match('/\.(vue\.stub|blade\.php)$/', $file->getFilename())) {
            $source = file_get_contents($file->getPathname());
            check(! preg_match('/font-family|fontFamily|font-\[/', $source), 'no inline or arbitrary font family in '.substr($file->getPathname(), strlen(dirname(__DIR__)) + 1));
            preg_match_all('/(?<![\w-])font-([a-z]+)(?![\w-])/', $source, $matches);
            $tokens = array_merge($tokens, $matches[1]);
        }
    }
    check(array_diff($tokens, WEIGHTS, ['sans', 'serif', 'mono']) === [], 'pages name no font family but font-sans, font-serif and font-mono');
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
    echo "Font checks render the component only; no network, browser or Tailwind build.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
