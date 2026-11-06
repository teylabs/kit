<?php

// Security mail on a synthetic app (SQLite memory, notifications faked, so nothing is sent): the account owner is told when their password,
// two-factor setup, recovery codes or passkeys change, and the old address is told when the email address changes. The mail is security mail
// (no manage link), says when and from where, and offers a password reset. kit.security_mail off sends none; a failing mailer never stops the change.
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Monolog\Handler\NullHandler;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Fortify\UpdateProfile;
use Tey\Kit\Features\SessionUi\Notifications\SecurityNotice;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function securityCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class SecurityUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

$root = sys_get_temp_dir().'/kit-security-mail-'.bin2hex(random_bytes(6));
mkdir($root.'/storage/framework/views', 0777, true);
file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));

try {
    $app = new Application($root);
    Container::setInstance($app);
    $kit = require dirname(__DIR__).'/config/kit.php';
    $app->instance('config', new Repository([
        'app' => ['name' => 'Northwind', 'url' => 'https://acme.test', 'locale' => 'en', 'fallback_locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'],
        'kit' => [...$kit, 'models' => ['user' => SecurityUser::class]],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'],
        'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'platform@acme.test', 'name' => 'Northwind']],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'expire_on_close' => false, 'encrypt' => false, 'lottery' => [0, 100]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, DatabaseServiceProvider::class, RoutingServiceProvider::class, TranslationServiceProvider::class, ViewServiceProvider::class, MailServiceProvider::class, NotificationServiceProvider::class, BusServiceProvider::class, QueueServiceProvider::class, ValidationServiceProvider::class, SessionServiceProvider::class, CacheServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->singleton(ExceptionHandler::class, fn () => new class implements ExceptionHandler
    {
        public array $reported = [];

        public function report(Throwable $e): void
        {
            $this->reported[] = $e->getMessage();
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function render($request, Throwable $e): Response
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e): void {}
    });
    $app->boot();
    $request = Request::create('https://acme.test/me', 'PUT', server: ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Synthetic/1.0']);
    $app->instance('request', $request);
    $app['router']->get('forgot-password', fn () => '')->name('password.request');
    $app['router']->getRoutes()->refreshNameLookups();
    Schema::create('users', function ($t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->timestamp('email_verified_at')->nullable();
        $t->string('password')->nullable();
        $t->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/resources/features/session-ui/migrations/*security_events*.stub') as $stub) {
        (require $stub)->up();
    }
    $ada = SecurityUser::create(['name' => 'Ada', 'email' => 'ada@example.test']);
    // Everything the faked dispatcher holds, as [to, type, new email]: 'user' for the account, else the routed address.
    $sentNotices = function (): array {
        $out = [];
        foreach ((fn () => $this->notifications)->call(Notification::getFacadeRoot()) as $byKey) {
            foreach ($byKey as $entries) {
                foreach ($entries[SecurityNotice::class] ?? [] as $entry) {
                    $out[] = [$entry['notifiable'] instanceof AnonymousNotifiable ? $entry['notifiable']->routes['mail'] : 'user', $entry['notification']->type, $entry['notification']->newEmail];
                }
            }
        }

        return $out;
    };

    Notification::fake();
    foreach (array_keys(SecurityNotice::MESSAGES) as $type) {
        if ($type !== 'email_changed') {
            SecurityLog::record($ada->id, $type, $request);
        }
    }
    $sent = Notification::sent($ada, SecurityNotice::class)->map(fn (SecurityNotice $n) => $n->type)->all();
    securityCheck($sent === ['password_changed', 'two_factor_confirmed', 'two_factor_disabled', 'recovery_codes_regenerated', 'passkey_added', 'passkey_removed'], 'each security change is mailed to the account owner');
    securityCheck(DB::table('kit_security_events')->where('user_id', $ada->id)->count() === 6, 'and is still written to the security log');

    Notification::fake();
    SecurityLog::record($ada->id, 'sign_in', $request);
    SecurityLog::record($ada->id, 'failed_sign_in', $request);
    SecurityLog::record($ada->id, 'two_factor_enabled', $request);
    securityCheck($sentNotices() === [], 'a sign-in, a failed sign-in and an unconfirmed two-factor setup send no mail');

    Notification::fake();
    (new UpdateProfile)->update($ada, ['name' => 'Ada', 'email' => 'ada@new.test']);
    $routed = $sentNotices();
    securityCheck(in_array(['ada@example.test', 'email_changed', 'ada@new.test'], $routed, true) && ! in_array(['user', 'email_changed', 'ada@new.test'], $routed, true), 'an email change is mailed to the old address, naming the new one');

    $mail = (new SecurityNotice('password_changed', 'Fri, Nov 6, 2026 11:00 AM UTC', '203.0.113.9', 'Synthetic/1.0'))->toMail($ada);
    $html = (string) preg_replace('/ style="[^"]*"/', '', (string) $mail->render());
    securityCheck($mail->subject === 'Your Northwind password was changed' && str_contains($html, '203.0.113.9') && str_contains($html, 'Synthetic/1.0') && str_contains($html, 'Fri, Nov 6, 2026'), 'the mail says what changed, when and from where');
    securityCheck(str_contains($html, '>Reset password<') && str_contains($html, 'https://acme.test/forgot-password') && ! str_contains($html, 'Manage notifications'), 'it offers a password reset and, as security mail, has no manage link');

    // A first password is set, not changed: no "your password was changed" mail, whether the account had none or was marked with awaitFirstPassword().
    foreach (glob(dirname(__DIR__).'/resources/features/session-ui/migrations/*create_kit_user_state_table*.stub') as $stub) {
        if (! Schema::hasTable('kit_user_state')) {
            (require $stub)->up();
        }
    }
    if (! $app['events']->hasListeners(PasswordReset::class)) {
        SecurityLog::listen($app['events']);
    }
    $types = fn (int $id): array => DB::table('kit_security_events')->where('user_id', $id)->orderBy('id')->pluck('type')->all();
    $resetTo = function (SecurityUser $user, string $password): void {
        $user->forceFill(['password' => 'hashed:'.$password])->save();
        event(new PasswordReset($user));
    };

    Notification::fake();
    $invitee = SecurityUser::create(['name' => 'Ivy', 'email' => 'ivy@example.test', 'password' => null]);
    $resetTo($invitee, 'first-password-1');
    securityCheck($sentNotices() === [] && $types($invitee->id) === ['password_set'], 'an account with no password setting its first one is logged as password_set and mailed nothing');
    $resetTo($invitee, 'second-password-2');
    securityCheck(Notification::sent($invitee, SecurityNotice::class)->map(fn (SecurityNotice $n) => $n->type)->all() === ['password_changed'], 'its next password change is mailed as a change');

    Notification::fake();
    $owner = SecurityUser::create(['name' => 'Owen', 'email' => 'owen@example.test', 'password' => 'unusable-'.bin2hex(random_bytes(24))]);
    SecurityLog::awaitFirstPassword($owner);
    $resetTo($owner, 'chosen-password-1');
    securityCheck($sentNotices() === [] && $types($owner->id) === ['password_set'], 'an owner provisioned with a random password and marked awaitFirstPassword() sets the first one without a mail');
    $resetTo($owner, 'chosen-password-2');
    securityCheck(Notification::sent($owner, SecurityNotice::class)->map(fn (SecurityNotice $n) => $n->type)->all() === ['password_changed'], 'the mark is cleared by that first set: the next change is mailed');

    Notification::fake();
    $kept = SecurityUser::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'hashed:existing']);
    $resetTo($kept, 'new-password-1');
    securityCheck(Notification::sent($kept, SecurityNotice::class)->count() === 1 && $types($kept->id) === ['password_changed'], 'an account that had a password, unmarked, is still mailed on a reset');

    Notification::fake();
    config(['kit.security_mail' => false]);
    SecurityLog::record($ada->id, 'password_changed', $request);
    securityCheck($sentNotices() === [], 'kit.security_mail off sends no security mail');
    config(['kit.security_mail' => true]);

} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}

// A mailer that fails is reported (scrubbed); the change it describes is still logged.
try {
    $before = DB::table('kit_security_events')->count();
    $app->instance(Dispatcher::class, new class implements Dispatcher
    {
        public function send($notifiables, $notification): void
        {
            throw new RuntimeException('transport down');
        }

        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('transport down');
        }
    });
    Facade::clearResolvedInstances();
    SecurityLog::record($ada->id, 'passkey_added', $request);
    $handler = $app->make(ExceptionHandler::class);
    securityCheck(DB::table('kit_security_events')->count() === $before + 1 && $handler->reported !== [] && str_contains(end($handler->reported), 'could not be sent') && ! str_contains(implode(' ', $handler->reported), '203.0.113.9'), 'a failing mailer is reported (scrubbed) and the change is still logged');
    echo "Security mail checks on SQLite memory with faked notifications: nothing is sent.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
