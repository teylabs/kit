<?php

// The kit's mail in light and dark, rendered on a synthetic app. This writes nothing unless asked: it renders the branded layout (a lockup drawn for a light
// background, the neutral brand and a light accent with dark text) and checks the parts a dark-mode client can't undo. The lockup is sent on its own opaque
// light plate, so dark ink stays on white wherever the header goes dark; the button is light text on a fill that holds 4.5:1; its label wraps only between
// words; the layout declares `light dark` with prefers-color-scheme and Outlook's [data-ogsc]/[data-ogsb] rules. It then compares the render with the
// snapshots in browser/mail/, which the browser suite opens in Chromium in light and dark (axe contrast, no mid-word wrap at phone width). After a change to the
// mail views, run it with --write to refresh them. No mail client, inbox or provider is used.
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Tey\Kit\Brand;
use Tey\Kit\Features\SessionUi\Notifications\AccountMail;
use Tey\Kit\Mail\FluentMailMessage;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);
$write = in_array('--write', $argv, true);

function renderCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$root = sys_get_temp_dir().'/kit-mail-render-'.bin2hex(random_bytes(8));
mkdir($root.'/storage/framework/views', 0777, true);
mkdir($root.'/storage/framework/cache', 0777, true);
mkdir($root.'/public', 0777, true);
file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
register_shutdown_function(function () use ($root): void {
    exec('rm -rf '.escapeshellarg($root));
});

try {
    renderCheck(function_exists('imagecreatefrompng'), 'GD is loaded (the lockup plate needs it)');
    $app = new Application($root);
    Container::setInstance($app);
    $kit = require dirname(__DIR__).'/config/kit.php';
    $kit['brand']['copyright'] = false;
    $app->instance('config', new Repository(['app' => ['name' => 'Northwind Farms', 'url' => 'https://acme.test', 'locale' => 'en', 'fallback_locale' => 'en'], 'kit' => $kit, 'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'], 'mail' => ['markdown' => ['theme' => 'default', 'paths' => []]], 'auth' => ['defaults' => ['passwords' => 'users'], 'passwords' => ['users' => ['expire' => 60]]]]));
    Facade::setFacadeApplication($app);
    $app->register(new FilesystemServiceProvider($app));
    $app->register(new TranslationServiceProvider($app));
    $app->register(new ViewServiceProvider($app));
    $app->register(new MailServiceProvider($app));
    $app->instance('request', Request::create('https://acme.test/'));
    $app['url']->setRequest($app['request']);
    $app['router']->get('reset-password/{token}', fn () => '')->name('password.reset');
    $app['router']->getRoutes()->refreshNameLookups();
    FluentMailMessage::appendMailPath(dirname(__DIR__).'/resources/views/mail');

    // A lockup drawn for a light background: near-black ink (a mark and a wordmark's bars) on transparency, 2x the 220px it shows at.
    $ink = imagecreatetruecolor(440, 80);
    imagealphablending($ink, false);
    imagesavealpha($ink, true);
    imagefill($ink, 0, 0, imagecolorallocatealpha($ink, 0, 0, 0, 127));
    $dark = imagecolorallocatealpha($ink, 24, 24, 27, 0);
    imagefilledellipse($ink, 40, 40, 64, 64, $dark);
    foreach ([[96, 18, 250, 34], [96, 46, 400, 62]] as [$x1, $y1, $x2, $y2]) {
        imagefilledrectangle($ink, $x1, $y1, $x2, $y2, $dark);
    }
    imagepng($ink, $root.'/public/mail-lockup.png');

    // The plate: opaque white at the corners' inside and the margin, the ink kept, the lockup 220px wide in the header.
    $plated = Brand::plateMailLockup($root.'/public/mail-lockup.png');
    renderCheck(is_string($plated) && is_file($plated), 'the lockup is plated');
    $image = imagecreatefrompng($plated);
    [$w, $h] = [imagesx($image), imagesy($image)];
    $rgba = fn (int $x, int $y): array => (static fn (int $c): array => [($c >> 16) & 255, ($c >> 8) & 255, $c & 255, ($c >> 24) & 127])(imagecolorat($image, $x, $y));
    renderCheck($w === 440 + 40 && $h === 80 + 40, 'the plate adds a margin of a quarter of the lockup\'s height on each side ('.$w.'x'.$h.')');
    renderCheck($rgba(12, 4) === [255, 255, 255, 0] && $rgba(intdiv($w, 2), 2) === [255, 255, 255, 0] && $rgba($w - 3, intdiv($h, 2)) === [255, 255, 255, 0], 'the margin is opaque white, so no dark header shows through it');
    renderCheck($rgba(0, 0)[3] === 127, 'the plate\'s corners are rounded (transparent outside the radius)');
    renderCheck(Brand::contrast(sprintf('#%02x%02x%02x', ...array_slice($rgba(20 + 40, 20 + 40), 0, 3)), '#ffffff') >= 4.5, 'the ink reads on the plate at 4.5:1 or more');
    renderCheck(Brand::plateMailLockup($root.'/public/mail-lockup.png') === $plated, 'the plate is made once per lockup (keyed by its content)');
    file_put_contents($root.'/public/broken.png', "\x89PNG\r\n\x1a\nnot really");
    renderCheck(Brand::plateMailLockup($root.'/public/broken.png') === null, 'a PNG GD can\'t read is not plated (the lockup goes as it is)');

    // The button: light text on its fill, 4.5:1 or more, for each kind of brand.
    foreach ([
        'the neutral brand' => [[], '#52525b', '#ffffff'],
        'a dark accent with white text' => [['accent' => '#1d4ed8', 'accent_foreground' => '#ffffff'], '#1d4ed8', '#ffffff'],
        'a light accent with dark text (its strong accent instead)' => [['accent' => '#f97316', 'accent_foreground' => '#18181b', 'accent_strong' => '#c2410c'], '#c2410c', '#ffffff'],
        'a light accent with no dark enough strong accent (ink instead)' => [['accent' => '#facc15', 'accent_foreground' => '#18181b', 'ink' => '#111827'], '#111827', '#ffffff'],
    ] as $label => [$brand, $background, $foreground]) {
        $button = Brand::mailButton(Brand::mailFor($brand));
        renderCheck($button === ['background' => $background, 'foreground' => $foreground] && Brand::contrast($foreground, $background) >= 4.5, 'the mail button for '.$label.' is '.$foreground.' on '.$background.' ('.round(Brand::contrast($foreground, $background), 2).':1)');
    }

    config(['kit.brand.mail_lockup' => '/mail-lockup.png']);
    Brand::applyToMail();
    $orange = ['accent' => '#f97316', 'accent_foreground' => '#18181b', 'accent_strong' => '#c2410c'];
    ResetPassword::$toMailCallback = null;
    AccountMail::register();
    $user = new class
    {
        public function getEmailForPasswordReset(): string
        {
            return 'ada@acme.test';
        }
    };
    $mails = [
        'reset-password' => (new ResetPassword('tok123'))->toMail($user),
        'sign-in-again' => (new FluentMailMessage)->as($orange)->subject('Northwind Ledger Online needs you to sign in again')->headline('Sign in to Northwind Ledger Online again')->paragraph('Northwind Ledger Online stopped accepting the saved connection, so new statements aren\'t coming in. Sign in again to pick up where it left off.')->button('Sign in again', 'https://acme.test/connections/7'),
        'long-label' => (new FluentMailMessage)->as($orange)->subject('A long button label')->paragraph('A label longer than the usual verb phrase still wraps only between words.')->button('Reconnect Northwind Ledger Online Accounts Portal', 'https://acme.test/connections/8'),
    ];
    $snapshots = dirname(__DIR__).'/browser/mail';
    foreach ($mails as $name => $mail) {
        $html = (string) $mail->render();
        renderCheck(str_contains($html, '<meta name="color-scheme" content="light dark">') && str_contains($html, '@media (prefers-color-scheme: dark)') && str_contains($html, '[data-ogsc]') && str_contains($html, '[data-ogsb]'), $name.': the layout declares light and dark, with prefers-color-scheme and Outlook\'s dark-mode rules');
        renderCheck(preg_match_all('~<a [^>]*class="button button-primary"[^>]*style="([^"]*)"~', $html, $buttons) === 1 && str_contains($buttons[1][0], 'word-break: keep-all') && ! str_contains($buttons[1][0], 'break-all'), $name.': the button label wraps only between words (keep-all, not the theme\'s break-all)');
        renderCheck(str_contains($html, 'src="kit-lockup:"') && preg_match('~<img src="kit-lockup:" width="220" height="55"~', $html) === 1, $name.': the header is the plated lockup at 220x55');
        // The snapshot shows the plated lockup from a file beside it: a browser can't resolve the cid the sent message uses.
        $html = str_replace('src="kit-lockup:"', 'src="lockup.png"', $html);
        $file = $snapshots.'/'.$name.'.html';
        if ($write) {
            file_put_contents($file, $html);
        }
        renderCheck(is_file($file) && file_get_contents($file) === $html, $name.': browser/mail/'.$name.'.html is the current render (run with --write after changing the mail views)');
    }
    if ($write) {
        copy($plated, $snapshots.'/lockup.png');
    }
    // Pixel by pixel, not byte for byte: GD's PNG encoding may differ between builds while the image is the same.
    $snapshot = is_file($snapshots.'/lockup.png') ? @imagecreatefrompng($snapshots.'/lockup.png') : false;
    $same = $snapshot !== false && imagesx($snapshot) === $w && imagesy($snapshot) === $h;
    for ($y = 0; $same && $y < $h; $y++) {
        for ($x = 0; $same && $x < $w; $x++) {
            $same = imagecolorat($snapshot, $x, $y) === imagecolorat($image, $x, $y);
        }
    }
    renderCheck($same, 'browser/mail/lockup.png is the current plated lockup, pixel for pixel (run with --write after changing the plate)');

    echo "Mail render checks passed. Rendered HTML and a GD image only; no mail client, inbox or provider was used.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
