<?php

// The kit's mail layout and account mail, on a synthetic app: Laravel's base layout with the lockup centred above, embedded inline (a CID attachment) and never a
// remote image, the product's name when there is no lockup; the password reset and email check in the kit's words with sentence-case buttons and no
// manage-notifications link; and links built on app.url, https, while the request (a TLS-terminating proxy, a worker) says http.
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Symfony\Component\Mime\Email;
use Tey\Kit\Brand;
use Tey\Kit\Features\SessionUi\Notifications\AccountMail;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\InlineBrandImages;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function lockupCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** A PNG of the given size: the signature and an IHDR, which is all getimagesize() reads. */
function lockupPng(string $path, int $width, int $height): void
{
    $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
    file_put_contents($path, "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND')));
}

$root = sys_get_temp_dir().'/kit-lockup-'.bin2hex(random_bytes(8));
mkdir($root.'/storage/framework/views', 0777, true);
mkdir($root.'/public', 0777, true);
file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
register_shutdown_function(function () use ($root): void {
    exec('rm -rf '.escapeshellarg($root));
});

try {
    $app = new Application($root);
    Container::setInstance($app);
    $kit = require dirname(__DIR__).'/config/kit.php';
    $app->instance('config', new Repository(['app' => ['name' => 'Northwind Farms', 'url' => 'https://acme.test', 'locale' => 'en', 'fallback_locale' => 'en'], 'kit' => $kit, 'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'], 'mail' => ['markdown' => ['theme' => 'default', 'paths' => []]], 'auth' => ['defaults' => ['passwords' => 'users'], 'passwords' => ['users' => ['expire' => 60]]]]));
    Facade::setFacadeApplication($app);
    $app->register(new FilesystemServiceProvider($app));
    $app->register(new TranslationServiceProvider($app));
    $app->register(new ViewServiceProvider($app));
    $app->register(new MailServiceProvider($app));
    // The request is http while the app is https: what a TLS-terminating proxy without trusted proxies, or a worker booted on a stale root, shows url().
    $app->instance('request', Request::create('http://acme.test/'));
    $app['url']->setRequest($app['request']);
    $app['router']->get('reset-password/{token}', fn () => '')->name('password.reset');
    $app['router']->get('email/verify/{id}/{hash}', fn () => '')->name('verification.verify');
    $app['router']->getRoutes()->refreshNameLookups();
    $app['url']->setKeyResolver(fn () => 'synthetic-key');
    FluentMailMessage::appendMailPath(dirname(__DIR__).'/resources/views/mail');
    $remoteImage = '/<img\b[^>]*\bsrc="(?:https?:|\/\/|data:)/i';

    // With a lockup: a wide PNG (1152x208), centred, at 220px with the height to its ratio, the product's name as alt, sent inline.
    lockupPng($root.'/public/mail-lockup.png', 1152, 208);
    config(['kit.brand.mail_lockup' => '/mail-lockup.png']);
    Brand::applyToMail();
    $html = (string) (new FluentMailMessage)->subject('Hi')->paragraph('Body')->button('Open', 'https://acme.test/a')->render();
    $bare = (string) preg_replace('/ style="[^"]*"/', '', $html);
    lockupCheck(preg_match('~<td class="header" align="center">\s*<a href="https://acme.test">\s*<img src="kit-lockup:" width="220" height="40" alt="Northwind Farms">\s*</a>~', $bare) === 1 && preg_match('~<img src="kit-lockup:"[^>]*style="[^"]*width: 220px; max-width: 100%; height: auto;~', $html) === 1, 'the header is the lockup centred above the message, linked to app.url, 220px wide and 40px high for a 1152x208 PNG, with the product\'s name as alt');
    lockupCheck(! str_contains($html, 'border-bottom: 2px solid') && ! str_contains($html, '<strong style="vertical-align: middle;">') && ! str_contains($html, 'kit-mark:'), 'the left-aligned mark, bold name and rule are gone');
    lockupCheck(str_contains($html, 'background-color: #52525b'), 'the button keeps the brand accent');
    lockupCheck(preg_match($remoteImage, $html) === 0, 'the rendered mail has no remote or data image');

    $email = (new Email)->from('updates@acme.test')->to('ada@acme.test')->subject('Hi')->html($html)->text('Body');
    InlineBrandImages::embed($email);
    $parts = array_values(array_filter($email->getAttachments(), fn ($part) => $part->getFilename() === 'kit-lockup'));
    lockupCheck(count($parts) === 1 && $parts[0]->getMediaType().'/'.$parts[0]->getMediaSubtype() === 'image/png' && str_contains((string) $email->getHtmlBody(), 'src="cid:kit-lockup"') && ! str_contains((string) $email->getHtmlBody(), 'kit-lockup:'), 'sending embeds the lockup as an inline PNG part and points the header at its cid');
    $raw = $email->toString();
    lockupCheck(preg_match('/Content-ID: <([^>]+)>/', $raw, $cid) === 1 && str_contains($raw, 'Content-Disposition: inline') && str_contains(quoted_printable_decode($raw), 'src="cid:'.$cid[1].'"'), 'the sent message carries the lockup as an inline part whose Content-ID the html references');

    // No lockup, a missing file or a non-PNG: the product's name, centred, and no image at all.
    foreach ([null, '/missing.png', $root.'/public/not-a-png.png'] as $lockup) {
        file_put_contents($root.'/public/not-a-png.png', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        config(['kit.brand.mail_lockup' => $lockup]);
        Brand::applyToMail();
        $html = (string) (new FluentMailMessage)->subject('Hi')->paragraph('Body')->render();
        lockupCheck(preg_match('~<td class="header" align="center">\s*<a href="https://acme.test">\s*Northwind Farms\s*</a>~', (string) preg_replace('/ style="[^"]*"/', '', $html)) === 1 && ! str_contains($html, '<img'), 'with mail_lockup '.var_export($lockup === null ? null : basename($lockup), true).' the header is the product\'s name, centred, with no image');
    }

    foreach (glob(dirname(__DIR__).'/resources/views/{mail/html,mail/text,fluent-mail}/*.blade.php', GLOB_BRACE) as $view) {
        lockupCheck(preg_match('/\bsrc\s*=\s*["\']?(?:https?:|\/\/|data:)/i', (string) file_get_contents($view)) === 0, 'no remote image in '.basename(dirname($view)).'/'.basename($view));
    }

    // Ordinary mail keeps the manage link, on app.url (https) though the request is http.
    lockupCheck(str_contains((string) preg_replace('/ style="[^"]*"/', '', $html), '<a href="https://acme.test/me">Manage notifications</a>'), 'the manage-notifications link is built on app.url: https, with an http request');

    // The account mail.
    ResetPassword::$toMailCallback = VerifyEmail::$toMailCallback = null;
    AccountMail::register();
    $user = new class
    {
        public function getEmailForPasswordReset(): string
        {
            return 'ada@acme.test';
        }

        public function getKey(): int
        {
            return 7;
        }

        public function getEmailForVerification(): string
        {
            return 'ada@acme.test';
        }
    };
    $reset = (new ResetPassword('tok123'))->toMail($user);
    $html = (string) $reset->render();
    $text = (string) $app->make(Markdown::class)->renderText($reset->markdown, $reset->data());
    lockupCheck($reset instanceof FluentMailMessage && $reset->subject === 'Reset your Northwind Farms password' && str_contains($html, 'Reset your password') && ! str_contains($html, 'Hello!') && ! str_contains($html, 'You are receiving this email'), 'the reset mail is in the kit\'s words, not Laravel\'s stock copy');
    lockupCheck(preg_match('~<a href="https://acme.test/reset-password/tok123\?email=ada%40acme.test" class="button button-primary"[^>]*>Reset password</a>~', $html) === 1 && ! str_contains($html, 'Reset Password'), 'its button is "Reset password", in sentence case, to the https link on app.url');
    lockupCheck(! str_contains($html, 'Manage notifications') && ! str_contains($text, 'Manage notifications') && str_contains($html, 'Northwind Farms'), 'the reset mail has no manage-notifications link, in the html or the text part');

    $verify = (new VerifyEmail)->toMail($user);
    $html = (string) $verify->render();
    $text = (string) $app->make(Markdown::class)->renderText($verify->markdown, $verify->data());
    lockupCheck($verify instanceof FluentMailMessage && str_contains($html, 'Verify your email address') && ! str_contains($html, 'Hello!') && ! str_contains($html, 'Please click the button'), 'the email check is in the kit\'s words');
    lockupCheck(preg_match('~class="button button-primary"[^>]*>Verify email address</a>~', $html) === 1 && ! str_contains($html, 'Verify Email Address'), 'its button is "Verify email address", in sentence case');
    lockupCheck(! str_contains($html, 'Manage notifications') && ! str_contains($text, 'Manage notifications'), 'the email check has no manage-notifications link');

    ResetPassword::$toMailCallback = $own = fn () => new FluentMailMessage;
    AccountMail::register();
    lockupCheck(ResetPassword::$toMailCallback === $own, 'a host\'s own ResetPassword::toMailUsing() is kept');

    echo "Mail lockup checks passed. Rendered HTML only; no mail client, inbox or browser was used.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
