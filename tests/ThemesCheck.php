<?php

// Theme presets (M1): the inventory, resolution order, the dev switch's bounds, the unthemed output byte for byte, fonts, kit:theme and 4.5:1
// per preset. A synthetic app, no network or browser; the presets are the kit's own, and the extra preset is invented.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Tey\Kit\Brand;
use Tey\Kit\BrandPreview;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Fonts;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Themes\Preset;
use Tey\Kit\Themes\Themes;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function themeCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $root = sys_get_temp_dir().'/kit-themes-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'themes'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([KitServiceProvider::class, FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();
    $shipped = require dirname(__DIR__).'/config/kit.php';
    config(['kit' => $shipped]);

    /** A request with its own session, as the dev switch sees it. */
    $request = function (array $query = [], ?Store $session = null) use ($app): Request {
        $request = Request::create('/', 'GET', $query);
        $request->setLaravelSession($session ?? new Store('test', new ArraySessionHandler(10)));
        $app->instance('request', $request);
        BrandPreview::apply($request);

        return $request;
    };

    // The inventory.
    $stable = ['appraisal', 'community-paper', 'funky-retro', 'market-grotesk', 'soft-studio', 'sunny-commons'];
    $drafts = ['retro-cooper', 'retro-evening', 'retro-groovy', 'retro-marquee', 'retro-sunburst', 'retro-sunny'];
    $everything = array_merge($stable, $drafts);
    sort($everything);
    themeCheck(array_keys(Themes::all()) === $everything, 'the kit ships the six presets and the six Funky Retro round-2 drafts');
    themeCheck(array_keys(Themes::offered()) === $stable, 'tenants are offered the stable presets only, never a draft');
    themeCheck(array_filter(Themes::all(), fn (Preset $p): bool => $p->isDraft() !== in_array($p->name, $drafts, true)) === [], 'the round-2 presets are all draft and the rest all stable');
    themeCheck(array_keys(Themes::find('appraisal')->values()) === Preset::KEYS, 'a preset sets every colour key, so it replaces the brand colours whole');
    config(['kit.themes.offered' => ['soft-studio', 'retro-sunny', 'nope']]);
    themeCheck(array_keys(Themes::offered()) === ['soft-studio'], 'kit.themes.offered narrows the list, and still never offers a draft or an unknown name');
    config(['kit.themes.offered' => null]);

    // 4.5:1 per preset, light and dark, through the existing guard.
    foreach (Themes::all() as $name => $preset) {
        config(['kit.themes.default' => $name]);
        $tokens = Brand::tokens();
        themeCheck(Brand::css($tokens) !== '' && Brand::contrastProblems($tokens) === [], "{$name}: prints its tokens and passes Brand::contrastProblems()");
    }
    config(['kit.themes.default' => null]);

    // WCAG 2.2 AA, light and dark: 4.5:1 for body, muted, link and button text, 3:1 for non-text UI.
    foreach (Themes::all() as $name => $preset) {
        $problems = Themes::accessibilityProblems($preset);
        themeCheck($problems === [], "{$name}: WCAG 2.2 AA in light and dark".($problems === [] ? '' : ': '.implode('; ', $problems)));
    }
    $weak = Preset::fromArray('weak', ['fonts' => ['heading' => 'Inter', 'body' => 'Inter'], 'light' => ['accent' => '#2563eb', 'background' => '#fafafa', 'muted_foreground' => '#a1a1aa', 'link' => '#93c5fd'], 'dark' => ['background' => '#18181b', 'foreground' => '#52525b']]);
    $weakProblems = implode(' | ', Themes::accessibilityProblems($weak));
    themeCheck(str_contains($weakProblems, 'weak (light): muted text on the page') && str_contains($weakProblems, 'weak (light): link on the page') && str_contains($weakProblems, 'weak (dark): body text on the page'), 'the AA check catches weak muted text, links and dark body text');
    themeCheck(str_contains(Brand::css(Brand::tokens(null, Themes::find('appraisal'))), '--foreground:#28231f;--muted-foreground:#52525b'), 'a preset prints its body and muted text colours');

    // The display face is only the short workspace-name mark.
    $marks = [];
    $named = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources', FilesystemIterator::SKIP_DOTS)) as $file) {
        $source = (string) file_get_contents($file->getPathname());
        if (str_contains($source, 'font-mark')) {
            $marks[] = $file->getFilename();
        }
        if (preg_match_all('/<(\w+)[^>]*\bdata-shell-(workspace|product-name)\b[^>]*>(.*?)<\/\1>/s', $source, $found, PREG_SET_ORDER)) {
            foreach ($found as $element) {
                $named++;
                themeCheck(preg_match('/\A\{\{\s*(name\(\)|app\.product\.name)\s*\}\}\z/', trim($element[3])) === 1, 'data-shell-'.$element[2].' in '.$file->getFilename().' holds only the name');
            }
        }
    }
    themeCheck($named >= 2 && $marks === [], 'no stub or view uses --font-mark itself: only <x-kit::fonts /> sets it, on the workspace and product name');

    // Unthemed: byte for byte what the base release printed (the fixture is origin/main's output for the same synthetic brand).
    [$head, $fonts, $branded] = explode("\n--\n", rtrim((string) file_get_contents(__DIR__.'/fixtures/themes/unthemed.txt'), "\n"));
    $app->instance('request', Request::create('/'));
    $unthemed = function () {
        return [Blade::render('<x-kit::brand />'), Blade::render('<x-kit::fonts />')];
    };
    foreach (['no themes block at all (an app whose published config predates themes)' => null, 'the shipped themes block with no KIT_THEME' => $shipped['themes']] as $label => $block) {
        config(['kit' => array_merge($shipped, ['themes' => $block])]);
        if ($block === null) {
            $all = config('kit');
            unset($all['themes']);
            config(['kit' => $all]);
        }
        [$h, $f] = $unthemed();
        themeCheck($h === $head && $f === $fonts, "unthemed, {$label}: <x-kit::brand /> and <x-kit::fonts /> are byte-identical to the base release");
        config(['kit.brand.accent' => '#2563eb', 'kit.brand.accent_strong' => '#1d4ed8', 'kit.brand.background' => '#fafaf9', 'kit.brand.link' => '#1d4ed8']);
        themeCheck(Blade::render('<x-kit::brand />') === $branded, "unthemed, {$label}: a configured brand prints exactly what it printed before");
    }
    config(['kit' => $shipped]);

    // Resolution order: the dev switch, then KIT_THEME, then the brand.
    $app['env'] = 'local';
    $request();
    themeCheck(Themes::active() === null && Brand::tokens()['accent'] === '#52525b', 'nothing set: no preset, the brand values');
    config(['kit.themes.default' => 'soft-studio']);
    themeCheck(Themes::active()?->name === 'soft-studio' && Brand::tokens()['accent'] === '#a8432a', 'KIT_THEME: the default preset is in force');
    $switched = $request(['theme' => 'appraisal']);
    themeCheck(Themes::active()?->name === 'appraisal' && $switched->session()->get(Themes::SESSION_KEY) === 'appraisal' && $switched->attributes->get('kit.theme') === 'appraisal', '?theme= wins over KIT_THEME and is kept in the session');
    $request([], $switched->session());
    themeCheck(Themes::active()?->name === 'appraisal', 'the session keeps the choice on the next request');
    $request(['theme' => 'retro-sunny'], $switched->session());
    themeCheck(Themes::active()?->name === 'retro-sunny', 'the switch can show a draft preset');
    $request(['theme' => ''], $switched->session());
    themeCheck(! $switched->session()->has(Themes::SESSION_KEY) && Themes::active()?->name === 'soft-studio', '?theme= with no value clears the session, back to KIT_THEME');
    $request(['theme' => 'appraisal'], $switched->session());
    $request(['theme' => 'no-such'], $switched->session());
    themeCheck(! $switched->session()->has(Themes::SESSION_KEY), 'an unknown ?theme= clears it too');
    config(['kit.themes.default' => 'typo']);
    try {
        Themes::active();
        themeCheck(false, 'an unknown KIT_THEME is refused');
    } catch (RuntimeException $error) {
        themeCheck(str_contains($error->getMessage(), 'Unknown theme preset: typo'), 'an unknown KIT_THEME is refused, naming the presets');
    }
    config(['kit.themes.default' => null]);

    // The switch's bounds: switch_envs, and never production.
    $app['env'] = 'staging';
    $staging = $request(['theme' => 'funky-retro']);
    themeCheck(Themes::active()?->name === 'funky-retro', 'staging is a switch environment by default');
    $app['env'] = 'production';
    config(['kit.themes.switch_envs' => ['production', 'local', 'staging']]);
    $request(['theme' => 'appraisal'], $staging->session());
    themeCheck(Themes::switchEnabled() === false && Themes::active() === null && $staging->session()->get(Themes::SESSION_KEY) === 'funky-retro', 'production: the switch is off even when switch_envs names it; a stored choice is neither read nor changed');
    config(['kit.themes.default' => 'community-paper']);
    themeCheck(Themes::active()?->name === 'community-paper', 'production still applies KIT_THEME');
    config(['kit.themes.default' => null, 'kit.themes.switch_envs' => ['local']]);
    $app['env'] = 'staging';
    $request(['theme' => 'appraisal'], $staging->session());
    themeCheck(Themes::active() === null && $staging->session()->get(Themes::SESSION_KEY) === 'funky-retro', 'an environment outside switch_envs ignores the switch');
    config(['kit.themes.switch_envs' => ['local', 'staging']]);
    $app['env'] = 'local';

    // An app's own presets through kit.themes.paths.
    file_put_contents($root.'/themes/invented.php', "<?php\nreturn ['label' => 'Invented', 'status' => 'draft', 'fonts' => ['heading' => 'Inter', 'body' => 'Inter'], 'light' => ['accent' => '#1d4ed8'], 'dark' => ['accent' => '#93c5fd']];\n");
    config(['kit.themes.paths' => [$root.'/themes']]);
    themeCheck(Themes::find('invented')?->label === 'Invented' && ! isset(Themes::offered()['invented']), 'kit.themes.paths adds an app\'s presets; a draft one is not offered');
    config(['kit.themes.paths' => []]);

    // Fonts: the preset's families from Bunny, never Google; the display face on the workspace name; self-hosted never reaches a CDN.
    $app->instance('request', Request::create('/'));
    config(['kit.themes.default' => 'funky-retro']);
    $html = Blade::render('<x-kit::fonts />');
    themeCheck(str_contains($html, 'https://fonts.bunny.net/css?family=dm-sans:400,500,600|shrikhand:400|ibm-plex-mono:400,500&display=swap') && str_contains($html, "--font-sans: 'DM Sans'") && str_contains($html, '--font-display-weight: 600;'), 'a preset loads its families through the Fonts component (Bunny)');
    themeCheck(str_contains($html, "--font-mark: 'Shrikhand'") && str_contains($html, '[data-shell-workspace], [data-shell-product-name] { font-family: var(--font-mark); }') && substr_count($html, 'var(--font-mark)') === 1 && substr_count($html, 'Shrikhand') === 1, 'the display face is set on the workspace-name mark and nowhere else');
    config(['kit.themes.default' => 'soft-studio']);
    themeCheck(! str_contains(Blade::render('<x-kit::fonts />'), '--font-mark'), 'a preset without a display face sets no mark');
    foreach (Themes::all() as $name => $preset) {
        config(['kit.themes.default' => $name]);
        $page = Blade::render('<x-kit::fonts />').Blade::render('<x-kit::brand />');
        if (str_contains($page, 'googleapis') || str_contains($page, 'gstatic')) {
            throw new RuntimeException("{$name} sends a Google Fonts URL");
        }
    }
    themeCheck(true, 'no preset sends a Google Fonts URL to the browser');
    config(['kit.fonts_source' => 'self', 'kit.themes.default' => 'appraisal']);
    @mkdir(public_path('vendor/kit/fonts'), 0700, true);
    foreach (Fonts::faces(Fonts::forPreset(Fonts::SETS['instrument'], Themes::find('appraisal'))) as $face) {
        touch(public_path('vendor/kit/fonts/'.$face['file']));
    }
    $self = Blade::render('<x-kit::fonts />');
    themeCheck(! str_contains($self, 'bunny.net') && str_contains($self, "--font-sans: 'Inter'"), 'self-hosted: the preset sets the families but never links a CDN');
    config(['kit.fonts_source' => 'cdn', 'kit.themes.default' => null]);

    // kit:theme lists, and sets KIT_THEME in the app's .env.
    file_put_contents($root.'/.env', "APP_NAME=Example\nKIT_THEME=\n");
    Artisan::call('kit:theme');
    $list = Artisan::output();
    themeCheck(str_contains($list, 'appraisal  Appraisal') && str_contains($list, 'retro-sunny  Sunny Retro  (draft)'), 'kit:theme lists the presets, drafts marked');
    themeCheck(Artisan::call('kit:theme', ['name' => 'community-paper']) === 0 && file_get_contents($root.'/.env') === "APP_NAME=Example\nKIT_THEME=community-paper\n", 'kit:theme <name> replaces KIT_THEME in .env');
    themeCheck(Artisan::call('kit:theme', ['name' => 'nope']) === 1 && file_get_contents($root.'/.env') === "APP_NAME=Example\nKIT_THEME=community-paper\n", 'kit:theme refuses an unknown name and writes nothing');
    file_put_contents($root.'/.env', "APP_NAME=Example\n");
    Artisan::call('kit:theme', ['name' => 'appraisal']);
    themeCheck(file_get_contents($root.'/.env') === "APP_NAME=Example\nKIT_THEME=appraisal\n", 'kit:theme adds KIT_THEME when it is missing');

    // The ?brand= switch is unchanged: still local only.
    config(['kit.brand.presets' => ['fill-one' => ['accent' => '#2563eb']]]);
    $app['env'] = 'local';
    $local = $request(['brand' => 'fill-one']);
    themeCheck($local->session()->get('kit.brand_preset') === 'fill-one', '?brand= still works locally');
    $app['env'] = 'staging';
    $staged = $request(['brand' => 'fill-one']);
    themeCheck(! $staged->session()->has('kit.brand_preset'), '?brand= still does nothing outside local');

    echo "ThemesCheck OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
