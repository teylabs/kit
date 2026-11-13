<?php

// The brand's sources (config/brand.php, the kit default; KIT_BRAND_* env is ignored since 0.17 and named), colour validation at boot, kit:brand:migrate
// and kit:status's source column and ignored-env warning, on synthetic apps in temp directories. No network, browser, database or consumer app.
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
    // A stale kit.brand (a host's published pre-0.17 config/kit.php, or a config cache built before the upgrade) is never a source: an unset key takes the default.
    $stale = new \Illuminate\Config\Repository(['kit' => ['brand' => ['accent' => '#112233', 'legal_name' => 'From env']], 'brand' => ['ink' => '#222222']]);
    $staleLogs = BrandConfig::resolve($stale, 'testing');
    check(count(array_filter($staleLogs, fn (array $log): bool => str_contains($log['message'], 'kit.brand.accent is set outside config/brand.php'))) === 1, 'a stale kit.brand value is named once as ignored');
    check($stale->get('kit.brand.accent') === BrandConfig::DEFAULTS['accent'] && $stale->get('kit.brand.legal_name') === BrandConfig::DEFAULTS['legal_name'] && $stale->get('kit.brand.ink') === '#222222'
        && $stale->get('kit.brand_sources.accent') === 'default' && $stale->get('kit.brand_sources.ink') === 'config', 'a stale kit.brand value is replaced by the default; config/brand.php still wins');
    check(! str_contains(logged($root), 'Deprecated: kit.brand'), 'no deprecation when nothing comes from env');

    // Since 0.17 KIT_BRAND_* env values are ignored: config/brand.php wins, an explicit null too, and a key it leaves out takes the kit default, never env.
    BrandConfig::forgetLogged();
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_ACCENT=\"#112233\"\nKIT_BRAND_LINK=\"#445566\"\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\nKIT_BRAND_MAIL_BUTTON=\"#0f0f0f\"\n",
        "<?php return ['accent' => '#abc', 'link' => null];");
    boot($root);
    check(config('kit.brand.accent') === '#aabbcc' && BrandConfig::sources()['accent'] === 'config', 'config/brand.php sets the accent (and a #rgb widens to #rrggbb)');
    check(config('kit.brand.link') === null && BrandConfig::sources()['link'] === 'config', 'an explicit null in config/brand.php stays null');
    check(config('kit.brand.legal_name') === 'Example Company' && BrandConfig::sources()['legal_name'] === 'default', 'a key config/brand.php leaves out takes the kit default, not its KIT_BRAND_* value');
    check(config('kit.brand.mail.button') === null && BrandConfig::sources()['mail.button'] === 'default', 'a nested key is not read from env either');
    check(array_values(array_unique(BrandConfig::sources())) === ['config', 'default'] || array_diff(BrandConfig::sources(), ['config', 'default']) === [], 'no key has an env source');
    check(array_diff(['accent', 'link', 'legal_name', 'mail.button'], (array) config('kit.brand_env')) === [], 'the set KIT_BRAND_* variables are recorded by name');

    // null in config/brand.php is "use the theme": the accent reads as an empty accent did (the theme's --primary for kit:brand), no error.
    $roots[] = $root = host("APP_ENV=testing\n", "<?php return ['accent' => null, 'card' => null];");
    boot($root);
    check(config('kit.brand.accent') === null && config('kit.brand.card') === null && Brand::accent() === '#52525b', 'null in config/brand.php uses the theme, with no error');
    check(config('kit.brand.accent', 'x') === 'x' && config('kit.brand.card', 'x') === 'x', 'an unset accent is null, so a config() default applies');

    // An already-migrated config/brand.php with '' in non-colour keys: each resolves to null (unset), so config() defaults apply, and the app boots in local.
    $roots[] = $root = host('APP_ENV=local
KIT_BRAND_IDENTITY=
', "<?php return ['wordmark' => '', 'logomark_dark' => ' ', 'credit_for' => '', 'accent' => null];");
    boot($root);
    check(config('kit.brand.wordmark') === null && config('kit.brand.logomark_dark') === null && config('kit.brand.credit_for') === null && config('kit.brand.identity') === null, "'' in config/brand.php reads as null, and an empty KIT_BRAND_IDENTITY is ignored");
    check(config('kit.brand.wordmark', 'x') === 'x' && config('kit.brand.identity', 'x') === 'x' && config('kit.brand.accent', 'x') === 'x' && Brand::accent() === '#52525b', "'' keys take the config() default; the accent draws the neutral default, never black");
    $nulls = array_filter(BrandConfig::DEFAULTS, fn ($default): bool => $default === null);
    check(array_filter(array_keys($nulls), fn (string $key): bool => config('kit.brand.'.$key) === '') === [], 'no brand key resolves to an empty string');

    // A KIT_BRAND_* still set logs one warning per variable, naming it as ignored and the way out, once per process.
    BrandConfig::forgetLogged();
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_ACCENT=\"#c2410c\"\nKIT_BRAND_HELLO_EMAIL=hello@example.test\n");
    boot($root);
    check(config('kit.brand.accent') === '#52525b' && config('kit.brand.hello_email') === 'hello@example.com', 'env values are not applied');
    check(substr_count(logged($root), 'KIT_BRAND_ACCENT is set but ignored: tey/kit 0.17 removed env brand values') === 1 && substr_count(logged($root), 'KIT_BRAND_HELLO_EMAIL is set but ignored') === 1 && str_contains(logged($root), 'kit:brand:migrate') && str_contains(logged($root), 'CI and deploy settings'), 'one warning per ignored variable, naming it, kit:brand:migrate, and where to remove it');
    check(! str_contains(logged($root), 'Deprecated: kit.brand'), 'no deprecation line any more');
    boot($root);
    check(substr_count(logged($root), 'KIT_BRAND_ACCENT is set but ignored') === 1, 'a second boot in the same process logs no second warning');

    // An invalid or empty KIT_BRAND_* colour no longer matters: it is ignored, so every boot starts in local and testing, and the colour is the kit default.
    foreach (['KIT_BRAND_INK=blue', 'KIT_BRAND_ACCENT=red', 'KIT_BRAND_CARD=', 'KIT_BRAND_ACCENT=#c2410c'] as $line) {
        foreach (['testing', 'local'] as $environment) {
            $roots[] = $root = host('APP_ENV='.$environment."\n".$line."\n");
            boot($root);
            check(config('kit.brand.ink') === '#09090b' && config('kit.brand.accent') === '#52525b' && config('kit.brand.card') === null && config('kit.brand_invalid') === [], $environment.': '.$line.' is ignored, and the boot starts');
        }
    }
    foreach (['card', 'accent'] as $key) {
        $roots[] = $root = host("APP_ENV=local\n", "<?php return ['".$key."' => ''];");
        check(refuses(fn () => boot($root), 'kit.brand.'.$key.' (config/brand.php) is empty'), 'local: an empty '.$key.' in config/brand.php throws');
    }

    // Production: an invalid config/brand.php colour is a warning, and the kit default; never empty.
    BrandConfig::forgetLogged();
    $roots[] = $root = host("APP_ENV=production\n", "<?php return ['accent_foreground' => 'zzz', 'mail' => ['button' => '']];");
    boot($root);
    check(config('kit.brand.accent_foreground') === '#ffffff' && config('kit.brand.mail.button') === null, 'production: an invalid colour takes the kit default');
    check(str_contains(logged($root), "kit.brand.accent_foreground (config/brand.php) is 'zzz'") && str_contains(logged($root), 'kit.brand.mail.button (config/brand.php) is empty') && str_contains(logged($root), 'Using the kit default'), 'production: each is logged as a warning');

    // The icon keys kit:brand reads: icon_colours mono or full, icon_padding 0 up to 0.5, icon_background a colour or none. Validated like the colours.
    $roots[] = $root = host("APP_ENV=local\n", "<?php return ['icon_colours' => 'full', 'icon_padding' => 0, 'icon_background' => '#FFF'];");
    boot($root);
    check(config('kit.brand.icon_colours') === 'full' && config('kit.brand.icon_padding') === 0.0 && config('kit.brand.icon_background') === '#ffffff', 'icon_colours full, a padding of 0 (as a float) and a #rgb background resolve');
    $roots[] = $root = host("APP_ENV=local\n", "<?php return ['icon_background' => 'none', 'icon_padding' => 0.49];");
    boot($root);
    check(config('kit.brand.icon_background') === 'none' && config('kit.brand.icon_padding') === 0.49 && config('kit.brand.icon_colours') === 'mono', 'icon_background takes none; icon_colours left out is mono');
    foreach (["'icon_colours' => 'colour'" => "kit.brand.icon_colours (config/brand.php) is 'colour', which is not 'mono' or 'full'", "'icon_padding' => 0.5" => 'kit.brand.icon_padding (config/brand.php) is 0.5, which is not a number from 0',
        "'icon_padding' => '0.1'" => "is '0.1', which is not a number", "'icon_background' => 'transparent'" => "kit.brand.icon_background (config/brand.php) is 'transparent', which is not a #rgb or #rrggbb colour, or 'none'. Set one, or null for the accent."] as $line => $message) {
        $roots[] = $root = host("APP_ENV=local\n", '<?php return ['.$line.'];');
        check(refuses(fn () => boot($root), $message), 'local: '.$line.' throws, naming what the key takes');
    }
    $roots[] = $root = host("APP_ENV=production\n", "<?php return ['icon_colours' => 'all', 'icon_padding' => -0.1, 'header_rule' => 'none'];");
    boot($root);
    check(config('kit.brand.icon_colours') === 'mono' && config('kit.brand.icon_padding') === null && config('kit.brand.header_rule') === null && str_contains(logged($root), 'kit.brand.icon_padding (config/brand.php) is -0.1'), 'production: an invalid icon key takes the kit default, logged; none is only for icon_background');

    // kit:status: the source column has only config and default.
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\n", "<?php return ['accent' => '#123456'];");
    file_put_contents($root.'/config/kit.php', "<?php return ['features' => ['core']];");
    boot($root);
    $sources = BrandConfig::sources();
    check($sources['accent'] === 'config' && $sources['legal_name'] === 'default' && $sources['ink'] === 'default' && count($sources) === count(BrandConfig::DEFAULTS), 'every brand key has a source, config or default');

    // config:cache: the cached config keeps the values and the names of the ignored variables, with .env no longer read.
    $roots[] = $root = host("APP_ENV=testing\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\n", "<?php return ['accent' => '#123456'];");
    $kernel = boot($root);
    check($kernel->call('config:cache') === 0 && is_file($root.'/bootstrap/cache/config.php'), 'config:cache succeeds with config/brand.php and an ignored KIT_BRAND_* set');
    file_put_contents($root.'/.env', "APP_ENV=testing\n");
    boot($root);
    check(config('kit.brand.accent') === '#123456' && config('kit.brand.legal_name') === 'Example Company' && config('kit.brand_env') === ['legal_name'], 'from the cached config: the same values, and the ignored variable still named');

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
    check(config('kit.brand.accent') === null && BrandConfig::sources()['accent'] === 'config' && config('kit.brand.mail.button') === '#0e7490', 'the migrated app boots in local, from config/brand.php');
    check(config('kit.brand.accent', 'x') === 'x', "a fresh migrate's unset accent is null, so config('kit.brand.accent', 'x') returns 'x'");

    // --force rewrites a '' an older config/brand.php kept in a non-colour key as null, even with no env to migrate; an empty colour is left as it is.
    $roots[] = $root = host("APP_ENV=local\n", "<?php return ['wordmark' => '', 'card' => ''];");
    $kernel = boot_migrate($root);
    check($kernel->call('kit:brand:migrate', ['--force' => true]) === 0 && (require $root.'/config/brand.php') === ['wordmark' => null, 'card' => ''], "--force writes a kept non-colour '' back as null, alone, and leaves an empty colour to be refused");

    // --force keeps a key config/brand.php already sets; it is never replaced by its env value.
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_INK=\"#111111\"\nKIT_BRAND_LEGAL_NAME=\"Env Co\"\n", "<?php return ['ink' => '#222222'];");
    $kernel = boot_migrate($root);
    check($kernel->call('kit:brand:migrate', ['--force' => true]) === 0 && (require $root.'/config/brand.php') === ['ink' => '#222222', 'legal_name' => 'Env Co'], '--force carries over a config-owned key and adds only the env-sourced ones');

    // A brand set in a published pre-0.17 config/kit.php, not in env: migrate writes what kit.brand merged to then, keys at the default left out, an invalid
    // colour flagged as null; a key with a KIT_BRAND_* variable still comes from env. It says the 'brand' array can go, and that other environments are copied by hand.
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_INK=\"#111111\"\n");
    file_put_contents($root.'/config/kit.php', "<?php return ['brand' => ['logomark' => '/logomark.svg', 'wordmark' => '', 'mail' => ['button' => '#0e7490'], 'accent' => '#C2410C', 'ink' => env('KIT_BRAND_INK', '#09090b'), 'card' => 'nope', 'legal_name' => 'Kit Co', 'lockup' => true]];");
    $kernel = boot_migrate($root);
    $code = $kernel->call('kit:brand:migrate');
    $out = $kernel->output();
    check($code === 0 && (require $root.'/config/brand.php') === ['lockup' => true, 'mail' => ['button' => '#0e7490'], 'accent' => '#c2410c', 'ink' => '#111111', 'card' => null, 'legal_name' => 'Kit Co'], "migrate writes config/kit.php's brand (defaults left out, the colour normalised, an invalid one as null) and the env key from env");
    check(str_contains($out, '1 key(s) from env, 4 from config/kit.php, and 1 flagged') && str_contains($out, "FLAGGED kit.brand.card: kit.brand.card in config/kit.php is 'nope'") && str_contains($out, '  KIT_BRAND_INK="#111111"') && ! str_contains($out, 'KIT_BRAND_ACCENT'), 'migrate counts each source, flags the invalid config/kit.php colour and lists only the env lines to delete');
    check(str_contains($out, "Delete its 'brand' array (written above: lockup, mail.button, accent, card, legal_name)") && str_contains($out, 'copy it into config/brand.php by hand'), "migrate says config/kit.php's brand can go, and that a value set only in another environment is copied by hand");
    forgetEnv();
    boot($root);
    check(config('kit.brand.accent') === '#c2410c' && BrandConfig::sources()['accent'] === 'config' && config('kit.brand.legal_name') === 'Kit Co' && config('kit.brand.card') === null, "the migrated app boots in local with config/kit.php's brand, from config/brand.php");
    // A variable set only in the process, after the config loaded (as with a cached config, whose kit.brand_env is the snapshot from the build), still wins over
    // config/kit.php. An invalid icon choice there is flagged as null, so the migrated app boots; a false that differs from the default is written.
    $roots[] = $root = host("APP_ENV=local\n");
    file_put_contents($root.'/config/kit.php', "<?php return ['brand' => ['icon_colours' => 'rainbow', 'icon_padding' => 0.25, 'legal_name' => 'Kit Co', 'postal_address' => 'Kit Street', 'copyright' => false]];");
    $kernel = boot_migrate($root);
    // A literal `null`, which env() reads as null, is still a variable that is set.
    foreach (['KIT_BRAND_LEGAL_NAME' => 'Deploy Co', 'KIT_BRAND_POSTAL_ADDRESS' => 'null'] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
    check($kernel->call('kit:brand:migrate') === 0 && (require $root.'/config/brand.php') === ['icon_colours' => null, 'icon_padding' => 0.25, 'legal_name' => 'Deploy Co', 'postal_address' => null, 'copyright' => false], 'process-only variables win over config/kit.php (a literal null one included, flagged as empty), an invalid icon_colours there is written as null, and copyright false is kept');
    check(str_contains($out = $kernel->output(), "FLAGGED kit.brand.icon_colours: kit.brand.icon_colours in config/kit.php is 'rainbow', not 'mono' or 'full'. null = the kit default") && str_contains($out, 'not .env (remove them where they are set: CI, deploy settings, scripts): KIT_BRAND_LEGAL_NAME'), 'migrate flags the icon choice and names the process-only variable');
    forgetEnv();
    boot($root);
    check(config('kit.brand.icon_colours') === null && config('kit.brand.icon_padding') === 0.25 && config('kit.brand.copyright') === false, 'the migrated app boots in local (icon_colours unset: kit:brand draws mono)');
    // Nothing to migrate still names the other environments; a config/kit.php that cannot be read fails, with nothing written.
    $roots[] = $root = host("APP_ENV=local\n");
    $kernel = boot_migrate($root);
    check($kernel->call('kit:brand:migrate') === 0 && str_contains($out = $kernel->output(), 'Nothing written') && str_contains($out, 'copy it into config/brand.php by hand') && ! is_file($root.'/config/brand.php'), 'with nothing to migrate, migrate writes nothing and names other environments');
    file_put_contents($root.'/config/kit.php', "<?php return ['brand' => ['logomark' => '/logomark.svg', 'accent' => '#52525B']];");
    check($kernel->call('kit:brand:migrate') === 0 && str_contains($out = $kernel->output(), "sets no brand other than the kit's defaults. Nothing written") && str_contains($out, "Delete its 'brand' array.") && ! is_file($root.'/config/brand.php'), "a config/kit.php brand of only the kit's defaults writes nothing and is still named for deletion");
    file_put_contents($root.'/config/kit.php', "<?php throw new RuntimeException('broken kit config');");
    check($kernel->call('kit:brand:migrate') === 1 && str_contains($kernel->output(), 'broken kit config') && ! is_file($root.'/config/brand.php'), 'a config/kit.php that cannot be read fails migrate, with nothing written');

    // kit:status names every KIT_BRAND_* still set, so a leftover in .env, CI or a deploy is found; it boots past any value.
    $roots[] = $root = host("APP_ENV=local\nKIT_BRAND_ACCENT=red\nKIT_BRAND_CARD=\n");
    file_put_contents($root.'/config/kit.php', "<?php return ['features' => ['core']];");
    check(BrandConfig::lenientCommand(['artisan', '--ansi', 'kit:brand:migrate', '--force']) && BrandConfig::lenientCommand(['artisan', 'kit:status', '--json'])
        && ! BrandConfig::lenientCommand(['artisan', 'kit:brand']) && ! BrandConfig::lenientCommand(['artisan', 'serve', 'kit:brand:migrate']) && ! BrandConfig::lenientCommand(['artisan']), 'the lenient command is the argv\'s command name, not any argument');
    $kernel = boot_as($root, ['artisan', 'kit:status']);
    $kernel->call('kit:status');
    $out = $kernel->output();
    check(str_contains($out, 'Ignored KIT_BRAND_* variables') && str_contains($out, 'KIT_BRAND_ACCENT') && str_contains($out, 'KIT_BRAND_CARD') && str_contains($out, 'kit:brand:migrate') && ! str_contains($out, 'is not a valid colour'), 'kit:status names each ignored variable and the way out, and lists none as invalid');
    $roots[] = $configRoot = host("APP_ENV=local\n", "<?php return ['card' => ''];");
    file_put_contents($configRoot.'/config/kit.php', "<?php return ['features' => ['core']];");
    $kernel = boot_as($configRoot, ['artisan', 'kit:status']);
    $kernel->call('kit:status');
    $out = $kernel->output();
    check(str_contains($out, 'kit.brand.card is not a valid colour') && str_contains($out, 'in config/brand.php') && ! str_contains($out, 'Ignored KIT_BRAND_'), 'kit:status: a bad config/brand.php colour is fixed in the file; no ignored-env line without a variable set');
    $roots[] = $iconRoot = host("APP_ENV=local\n", "<?php return ['icon_padding' => 2, 'icon_background' => 'clear', 'icon_colours' => 'rainbow'];");
    file_put_contents($iconRoot.'/config/kit.php', "<?php return ['features' => ['core']];");
    $kernel = boot_as($iconRoot, ['artisan', 'kit:status']);
    $kernel->call('kit:status');
    $out = $kernel->output();
    check(str_contains($out, 'kit.brand.icon_padding is not valid and is using the kit default: set a number from 0 up to (not including) 0.5, or null')
        && str_contains($out, "kit.brand.icon_colours is not valid and is using the kit default: set 'mono' or 'full', or null")
        && str_contains($out, "kit.brand.icon_background is not valid and is using the kit default: set a #rgb or #rrggbb colour, or 'none', or null")
        && ! str_contains($out, 'kit.brand.icon_padding is not a valid colour'), 'kit:status: a bad icon key names what it takes, not a colour');
    foreach ([['artisan', 'kit:brand'], ['artisan', 'serve'], ['vendor/bin/pest']] as $argv) {
        boot_as($root, $argv);
        check(config('kit.brand.accent') === '#52525b', 'local, KIT_BRAND_ACCENT=red: '.implode(' ', $argv).' boots, with the kit default');
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
