<?php

// The brand's sources (config/brand.php, the deprecated KIT_BRAND_* env, the kit default), colour validation at boot, kit:brand:migrate and kit:status's
// source column, on synthetic apps in temp directories. No network, browser, database or consumer app.
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\Brand;
use Tey\Kit\BrandConfig;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
require __DIR__.'/InstalledSeam.php';
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** Forgets every variable a previous app's .env put in the process (Dotenv is immutable, so it would otherwise stick). */
function forgetEnv(): void
{
    foreach (array_unique([...array_keys($_ENV), ...array_keys($_SERVER), ...array_keys(getenv())]) as $name) {
        if (is_string($name) && (str_starts_with($name, 'KIT_BRAND_') || in_array($name, ['APP_ENV', 'APP_KEY', 'APP_CONFIG_CACHE'], true))) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    }
}

function host(string $env, ?string $brand = null): string
{
    $root = sys_get_temp_dir().'/kit-brandconfig-'.bin2hex(random_bytes(6));
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views', 'config', 'public'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/.env', $env);
    if ($brand !== null) {
        file_put_contents($root.'/config/brand.php', $brand);
    }
    // config:cache builds a fresh app from bootstrap/app.php.
    file_put_contents($root.'/bootstrap/app.php', "<?php\nreturn Illuminate\\Foundation\\Application::configure(dirname(__DIR__))->withExceptions(fn (\$e) => null)->withProviders([Tey\\Mod\\ModServiceProvider::class, Tey\\Kit\\KitServiceProvider::class, Tey\\Kit\\Features\\FeaturesServiceProvider::class])->create();\n");

    return realpath($root);
}

function boot(string $root): Kernel
{
    forgetEnv();
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class, FeaturesServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return $kernel;
}

function logged(string $root): string
{
    return is_file($root.'/storage/logs/laravel.log') ? (string) file_get_contents($root.'/storage/logs/laravel.log') : '';
}

function refuses(Closure $operation, string $contains): bool
{
    try {
        $operation();
    } catch (Throwable $error) {
        return str_contains($error->getMessage(), $contains);
    }

    return false;
}

$roots = [];
try {
    simulateInstalled('tey/concierge', '0.7.6');

    // The stub carries every key, commented out at the kit's default; the defaults match config/kit.php with no env set.
    $stub = (string) file_get_contents(dirname(__DIR__).'/resources/stubs/brand.php');
    check((require dirname(__DIR__).'/resources/stubs/brand.php') === [], 'the published stub sets nothing: every key is commented out');
    $missing = array_filter(array_keys(BrandConfig::DEFAULTS), fn (string $key): bool => ! str_contains($stub, "'".(str_contains($key, '.') ? substr($key, 5) : $key)."' => "));
    check($missing === [], 'the stub names every brand key');

    // Neither set: the default, source default.
    $roots[] = $root = host("APP_ENV=testing\n");
    boot($root);
    check(config('kit.brand.accent') === '#52525b' && config('kit.brand.legal_name') === 'Example Company' && config('kit.brand.mail.button') === null, 'neither config nor env: the kit default');
    check(BrandConfig::sources()['accent'] === 'default' && BrandConfig::sources()['mail.button'] === 'default', 'the source is default');
    $defaults = [];
    foreach (BrandConfig::DEFAULTS as $key => $default) {
        $defaults[$key] = data_get(config('kit.brand'), $key);
    }
    check($defaults === BrandConfig::DEFAULTS, 'BrandConfig::DEFAULTS matches config/kit.php with no env set');
    check(! str_contains(logged($root), 'Deprecated: kit.brand'), 'no deprecation when nothing comes from env');

    // config/brand.php beats env; an explicit null beats env too; a key it leaves out falls back to env.
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_ACCENT=\"#112233\"\nKIT_BRAND_LINK=\"#445566\"\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\nKIT_BRAND_MAIL_BUTTON=\"#0f0f0f\"\n",
        "<?php return ['accent' => '#abc', 'link' => null, 'mail' => ['button' => '#123456']];");
    boot($root);
    check(config('kit.brand.accent') === '#aabbcc' && BrandConfig::sources()['accent'] === 'config', 'config/brand.php beats env (and a #rgb widens to #rrggbb)');
    check(config('kit.brand.link') === null && BrandConfig::sources()['link'] === 'config', 'an explicit null in config/brand.php beats env');
    check(config('kit.brand.mail.button') === '#123456' && BrandConfig::sources()['mail.button'] === 'config', 'a nested key in config/brand.php beats env');
    check(config('kit.brand.legal_name') === 'Env Co' && BrandConfig::sources()['legal_name'] === 'env (deprecated)', 'a key config/brand.php leaves out falls back to env');

    // null in config/brand.php is "use the theme": the accent reads as an empty accent did (the theme's --primary for kit:brand), no error.
    $roots[] = $root = host("APP_ENV=testing\n", "<?php return ['accent' => null, 'card' => null];");
    boot($root);
    check(config('kit.brand.accent') === '' && config('kit.brand.card') === null && Brand::accent() === '#52525b', 'null in config/brand.php uses the theme, with no error');

    // Env fallback logs one deprecation per key, once per process.
    BrandConfig::forgetLogged();
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_ACCENT=\"#c2410c\"\nKIT_BRAND_HELLO_EMAIL=hello@example.test\n");
    boot($root);
    check(config('kit.brand.accent') === '#c2410c' && config('kit.brand.hello_email') === 'hello@example.test', 'env fallback works');
    check(substr_count(logged($root), 'Deprecated: kit.brand.accent comes from KIT_BRAND_ACCENT') === 1 && substr_count(logged($root), 'kit.brand.hello_email comes from KIT_BRAND_HELLO_EMAIL') === 1 && str_contains(logged($root), 'kit:brand:migrate'), 'one deprecation per env key, naming it and kit:brand:migrate');
    boot($root);
    check(substr_count(logged($root), 'Deprecated: kit.brand.accent') === 1, 'a second boot in the same process logs no second deprecation');

    // An invalid env colour throws in local and testing, naming the key.
    foreach (["KIT_BRAND_INK=blue\n" => "kit.brand.ink (KIT_BRAND_INK) is 'blue'", "KIT_BRAND_ACCENT=red\n" => "kit.brand.accent (KIT_BRAND_ACCENT) is 'red'"] as $line => $named) {
        foreach (['testing', 'local'] as $environment) {
            $roots[] = $root = host('APP_ENV='.$environment."\n".$line);
            check(refuses(fn () => boot($root), $named), $environment.': '.trim($line).' throws, naming the key');
        }
    }

    // A key cached as invalid that now loads empty from env leaves kit.brand_invalid.
    $repo = new Repository(['kit' => ['brand' => ['accent' => ''], 'brand_env' => ['accent'], 'brand_invalid' => ['accent']]]);
    BrandConfig::resolve($repo, 'local');
    check($repo->get('kit.brand_invalid') === [] && $repo->get('kit.brand_env_empty') === ['accent'] && $repo->get('kit.brand.accent') === '', 'an empty env colour is dropped from a stale kit.brand_invalid');

    // An empty env colour (an unquoted #hex read as a comment) is "use the theme", as before 0.15.49: no throw, on any boot, and a hint once per key.
    BrandConfig::forgetLogged();
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_ACCENT=#c2410c\nKIT_BRAND_CARD=\n");
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'kit:update', 'plan'];
    $kernel = boot($root);
    $_SERVER['argv'] = $argv;
    check(config('kit.brand.accent') === '' && Brand::accent() === '#52525b' && config('kit.brand.card') === null && BrandConfig::sources()['accent'] === 'env (deprecated)', 'local, empty env accent: a strict boot (kit:update plan) starts, and the accent uses the theme');
    check(! in_array('accent', (array) config('kit.brand_invalid'), true) && config('kit.brand_env_empty') === ['accent', 'card'], 'an empty env colour is not invalid; it is recorded as empty in env');
    boot($root);
    $hint = 'kit.brand.accent (KIT_BRAND_ACCENT) is empty in env, so it uses the theme';
    check(substr_count(logged($root), $hint) === 1 && substr_count(logged($root), 'Deprecated: kit.brand.accent comes from KIT_BRAND_ACCENT') === 1 && str_contains(logged($root), 'kit:brand:migrate') && substr_count(logged($root), 'kit.brand.card (KIT_BRAND_CARD) is empty in env') === 1, 'the deprecation and the empty-value hint are each logged once per key, over two boots');
    foreach (['card', 'accent'] as $key) {
        $roots[] = $root = host("APP_ENV=local\n", "<?php return ['".$key."' => ''];");
        check(refuses(fn () => boot($root), 'kit.brand.'.$key.' (config/brand.php) is empty'), 'local: an empty '.$key.' in config/brand.php throws');
    }

    // Production: a warning, and the kit default; never empty.
    BrandConfig::forgetLogged();
    $roots[] = $root = host("APP_ENV=production\nKIT_BRAND_ACCENT=#c2410c\nKIT_BRAND_ACCENT_FOREGROUND=zzz\n", "<?php return ['mail' => ['button' => '']];");
    boot($root);
    check(config('kit.brand.accent') === '' && config('kit.brand.accent_foreground') === '#ffffff' && config('kit.brand.mail.button') === null, 'production: an invalid colour takes the kit default, an empty env accent the theme');
    check(str_contains(logged($root), 'kit.brand.accent (KIT_BRAND_ACCENT) is empty in env') && str_contains(logged($root), "kit.brand.accent_foreground (KIT_BRAND_ACCENT_FOREGROUND) is 'zzz'") && str_contains(logged($root), 'kit.brand.mail.button (config/brand.php) is empty') && str_contains(logged($root), 'Using the kit default'), 'production: each is logged as a warning');

    // kit:status: the source column.
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\n", "<?php return ['accent' => '#123456'];");
    file_put_contents($root.'/config/kit.php', "<?php return ['features' => ['core']];");
    $kernel = boot($root);
    $sources = BrandConfig::sources();
    check($sources['accent'] === 'config' && $sources['legal_name'] === 'env (deprecated)' && $sources['ink'] === 'default' && count($sources) === count(BrandConfig::DEFAULTS), 'every brand key has a source');

    // config:cache: the cached config keeps the values and the env answer, with .env no longer read.
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\n", "<?php return ['accent' => '#123456'];");
    $kernel = boot($root);
    check($kernel->call('config:cache') === 0 && is_file($root.'/bootstrap/cache/config.php'), 'config:cache succeeds with config/brand.php and brand_env');
    file_put_contents($root.'/.env', "APP_ENV=testing\n");
    boot($root);
    check(config('kit.brand.accent') === '#123456' && config('kit.brand.legal_name') === 'Env Co' && BrandConfig::sources()['legal_name'] === 'env (deprecated)' && BrandConfig::sources()['accent'] === 'config', 'from the cached config: the same values and sources');

    // kit:brand:migrate: writes the env keys at their loaded values, flags the empty ones as null, prints the lines to delete, never edits .env.
    $env = "APP_ENV=local\nAPP_NAME=Example\nKIT_BRAND_ACCENT=#c2410c\nKIT_BRAND_INK=\"#111111\"\nKIT_BRAND_LEGAL_NAME=\"Example Studio Ltd\"\nKIT_BRAND_MAIL_BUTTON='#0e7490'\nKIT_BRAND_LOCKUP=true\nKIT_BRAND_PRESETS='{\"dusk\":{\"accent\":\"#7c3aed\"}}'\n";
    $roots[] = $root = host($env);
    $kernel = boot_migrate($root);
    $code = $kernel->call('kit:brand:migrate');
    $out = $kernel->output();
    echo "--- kit:brand:migrate output ---\n".$out."--- config/brand.php ---\n".file_get_contents($root.'/config/brand.php')."---\n";
    $written = require $root.'/config/brand.php';
    check($code === 0 && $written === ['lockup' => true, 'mail' => ['button' => '#0e7490'], 'accent' => null, 'ink' => '#111111', 'presets' => ['dusk' => ['accent' => '#7c3aed']], 'legal_name' => 'Example Studio Ltd'], 'migrate writes only the env keys, at their loaded values, and the empty one as null');
    check(str_contains((string) file_get_contents($root.'/config/brand.php'), '// KIT_BRAND_ACCENT loaded empty (unquoted #?). null = use theme; set a value to override.') && ! str_contains((string) file_get_contents($root.'/config/brand.php'), 'c2410c'), 'the #-truncated key is flagged, never guessed');
    check(str_contains($out, 'FLAGGED kit.brand.accent') && str_contains($out, '  KIT_BRAND_ACCENT=#c2410c') && str_contains($out, '  KIT_BRAND_INK="#111111"'), 'migrate prints the flagged key and the .env lines to delete');
    check(file_get_contents($root.'/.env') === $env, 'migrate never edits .env');
    $before = (string) file_get_contents($root.'/config/brand.php');
    check($kernel->call('kit:brand:migrate') === 1 && str_contains($kernel->output(), '--force') && file_get_contents($root.'/config/brand.php') === $before, 'migrate refuses to overwrite config/brand.php without --force');
    check($kernel->call('kit:brand:migrate', ['--force' => true]) === 0 && file_get_contents($root.'/.env') === $env, '--force overwrites, and still leaves .env alone');
    // After migrating, the app boots in local with no error: config/brand.php now wins, and accent null uses the theme.
    forgetEnv();
    boot($root);
    check(config('kit.brand.accent') === '' && BrandConfig::sources()['accent'] === 'config' && config('kit.brand.mail.button') === '#0e7490', 'the migrated app boots in local, from config/brand.php');

    // --force keeps a key config/brand.php already sets; it is never replaced by its env value.
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_INK=\"#111111\"\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\n", "<?php return ['ink' => '#222222'];");
    $kernel = boot_migrate($root);
    check($kernel->call('kit:brand:migrate', ['--force' => true]) === 0 && (require $root.'/config/brand.php') === ['ink' => '#222222', 'legal_name' => 'Env Co'], '--force carries over a config-owned key and adds only the env-sourced ones');

    // From a cached config with no .env left: the cached env values are migrated, and a cached empty value is still flagged, not lost.
    $roots[] = $root = host("APP_ENV=production\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\nKIT_BRAND_ACCENT=#c2410c\n");
    $kernel = boot($root);
    check($kernel->call('config:cache') === 0, 'config:cache with env brand values');
    unlink($root.'/.env');
    $kernel = boot_migrate($root);
    check($kernel->call('kit:brand:migrate') === 0 && (require $root.'/config/brand.php') === ['accent' => null, 'legal_name' => 'Env Co'] && str_contains((string) file_get_contents($root.'/config/brand.php'), 'KIT_BRAND_ACCENT loaded empty'), 'migrate from a cached config keeps the cached value and flags the empty one');

    // Regression (0.15.49, HC): an empty env accent in local uses the theme on every boot; migrate still flags it; kit:status never lists it as invalid.
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_ACCENT=#c2410c\n");
    check(BrandConfig::lenientCommand(['artisan', '--ansi', 'kit:brand:migrate', '--force']) && BrandConfig::lenientCommand(['artisan', 'kit:status', '--json'])
        && ! BrandConfig::lenientCommand(['artisan', 'kit:brand']) && ! BrandConfig::lenientCommand(['artisan', 'serve', 'kit:brand:migrate']) && ! BrandConfig::lenientCommand(['artisan']), 'the lenient command is the argv\'s command name, not any argument');
    $kernel = boot_as($root, ['artisan', '--ansi', 'kit:brand:migrate']);
    check($kernel->call('kit:brand:migrate') === 0 && (require $root.'/config/brand.php') === ['accent' => null] && str_contains((string) file_get_contents($root.'/config/brand.php'), 'KIT_BRAND_ACCENT loaded empty'), 'local, empty env accent: kit:brand:migrate runs and writes null with its comment');
    unlink($root.'/config/brand.php');
    file_put_contents($root.'/config/kit.php', "<?php return ['features' => ['core']];");
    $kernel = boot_as($root, ['artisan', 'kit:status']);
    check(config('kit.brand.accent') === '' && config('kit.brand_invalid') === [] && config('kit.brand_env_empty') === ['accent'], 'local, empty env accent: kit:status boots, the accent uses the theme and is not invalid');
    $kernel->call('kit:status');
    $out = $kernel->output();
    check(! str_contains($out, 'is not a valid colour'), 'kit:status does not list an empty env colour as invalid');
    foreach ([['artisan', 'kit:brand'], ['artisan', 'serve'], ['artisan', 'kit:update', 'plan'], ['vendor/bin/pest']] as $argv) {
        boot_as($root, $argv);
        check(config('kit.brand.accent') === '', 'local, empty env accent: '.implode(' ', $argv).' boots with the theme accent');
    }
    // A non-empty invalid env colour: kit:status still boots and names it with the way out; every other boot still throws.
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_ACCENT=red\n");
    file_put_contents($root.'/config/kit.php', "<?php return ['features' => ['core']];");
    $kernel = boot_as($root, ['artisan', 'kit:status']);
    $kernel->call('kit:status');
    $out = $kernel->output();
    check(config('kit.brand.accent') === '#52525b' && str_contains($out, 'kit.brand.accent is not a valid colour') && str_contains($out, 'kit:brand:migrate'), 'kit:status names an invalid env key and the way out');
    $roots[] = $configRoot = host("APP_ENV=local\n", "<?php return ['card' => ''];");
    file_put_contents($configRoot.'/config/kit.php', "<?php return ['features' => ['core']];");
    $kernel = boot_as($configRoot, ['artisan', 'kit:status']);
    $kernel->call('kit:status');
    $out = $kernel->output();
    check(str_contains($out, 'kit.brand.card is not a valid colour') && str_contains($out, 'in config/brand.php') && ! str_contains($out, 'kit:brand:migrate, or'), 'kit:status: a bad config/brand.php colour is fixed in the file, not by migrate');
    foreach ([['artisan', 'kit:brand'], ['artisan', 'serve'], ['artisan', 'list', 'kit:brand:migrate'], ['vendor/bin/pest']] as $argv) {
        check(refuses(fn () => boot_as($root, $argv), "kit.brand.accent (KIT_BRAND_ACCENT) is 'red'"), 'local, KIT_BRAND_ACCENT=red: '.implode(' ', $argv).' still throws');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n");
    exit(1);
} finally {
    forgetEnv();
    foreach ($roots as $root) {
        exec('rm -rf '.escapeshellarg($root));
    }
}

/** Boots with the given argv, as that artisan command would. */
function boot_as(string $root, array $argv): Kernel
{
    $saved = $_SERVER['argv'];
    $_SERVER['argv'] = $argv;
    try {
        return boot($root);
    } finally {
        $_SERVER['argv'] = $saved;
    }
}

/** Boots as `php artisan kit:brand:migrate` would: the colour check doesn't throw, since migrate is the way out of an invalid env value. */
function boot_migrate(string $root): Kernel
{
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'kit:brand:migrate'];
    try {
        return boot($root);
    } finally {
        $_SERVER['argv'] = $argv;
    }
}
