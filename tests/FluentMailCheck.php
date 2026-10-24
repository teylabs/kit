<?php

// FluentMailMessage and the mail conformance scanner, on a synthetic app with no provider of the kit's: every block renders through the layout, escaped by
// default, text part without the preheader, macroable, and the scanner flags a Mailable, the Mail facade and a stock MailMessage.
use Illuminate\Foundation\Application;
use Illuminate\Mail\Markdown;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\MailConformance;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function mailCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function tidy(FluentMailMessage $mail): string
{
    return (string) preg_replace('/ style="[^"]*"/', '', (string) $mail->render());
}

$root = sys_get_temp_dir().'/kit-mail-'.bin2hex(random_bytes(8));
mkdir($root.'/storage/framework/views', 0777, true);
file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
register_shutdown_function(function () use ($root): void {
    exec('rm -rf '.escapeshellarg($root));
});

try {
    $app = new Application($root);
    Illuminate\Container\Container::setInstance($app);
    $app->instance('config', new Illuminate\Config\Repository(['app' => ['name' => 'Synthetic', 'url' => 'https://example.test', 'locale' => 'en', 'fallback_locale' => 'en'], 'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'], 'mail' => ['markdown' => ['theme' => 'default', 'paths' => []]]]));
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    $app->register(new Illuminate\Filesystem\FilesystemServiceProvider($app));
    $app->register(new Illuminate\Events\EventServiceProvider($app));
    $app->register(new Illuminate\Translation\TranslationServiceProvider($app));
    $app->register(new ViewServiceProvider($app));
    $app->register(new MailServiceProvider($app));

    $mail = (new FluentMailMessage)->subject('Hi')->preheader('Quick look inside')->headline('Report <b>ready</b>')
        ->paragraph('Value is *not* bold & <script>alert(1)</script>')
        ->facts(['Client' => 'Jane_Doe', 'Skipped' => null])
        ->table(['Item', 'Amount'], [['Pipe | split', '$10']])
        ->panel('Panel text')->callout('Heads up', 'Expires soon')
        ->bullets(['- one', 'two'])->quote('> quoted')->divider()
        ->section('Next steps', fn (FluentMailMessage $m) => $m->paragraph('Inside section'))
        ->button('Open', 'https://example.test/a')->button('Details', 'https://example.test/b', 'secondary')
        ->markdown('**raw bold**');
    $html = tidy($mail);

    mailCheck(str_contains($html, 'Quick look inside'), 'the preheader reaches the html part');
    mailCheck(str_contains($html, '<h1>Report &lt;b&gt;ready&lt;/b&gt;</h1>') && ! str_contains($html, '<script>') && ! str_contains($html, '<em>not</em>'), 'text is escaped by default, HTML and markdown');
    mailCheck(str_contains($html, '<strong>Client:</strong> Jane_Doe') && ! str_contains($html, 'Skipped'), 'facts render bold labels and skip empty values');
    mailCheck(str_contains($html, '<th>Item</th>') && str_contains($html, 'Pipe | split'), 'a table escapes its pipes');
    mailCheck(str_contains($html, 'Panel text') && str_contains($html, '<strong>Heads up</strong>') && str_contains($html, '- one'), 'panel, callout and escaped bullets render');
    mailCheck(str_contains($html, '<blockquote>') && str_contains($html, '<hr') && str_contains($html, '<h2>Next steps</h2>'), 'quote, divider and section render');
    mailCheck(str_contains($html, 'href="https://example.test/a"') && str_contains($html, '<a href="https://example.test/b">Details</a>'), 'primary buttons and secondary links render');
    mailCheck(str_contains($html, '<strong>raw bold</strong>'), 'markdown() is the explicit raw escape hatch');

    $text = (string) $app->make(Markdown::class)->renderText($mail->markdown, $mail->data());
    mailCheck(str_contains($text, 'Open: https://example.test/a') && ! str_contains($text, 'Quick look inside'), 'the text part lists button URLs and omits the preheader');

    FluentMailMessage::macro('signoff', fn () => $this->note('The team'));
    mailCheck(str_contains(tidy((new FluentMailMessage)->signoff()), '<em>The team</em>'), 'the composer is macroable');
    mailCheck(count(array_filter((array) config('mail.markdown.paths'), fn ($path) => str_ends_with($path, 'fluent-mail'))) === 1, 'registering twice adds the component path once');

    $mail = (new FluentMailMessage)->greeting('Hello!')->line('One')->action('Go', 'https://example.test/go');
    mailCheck($mail->actionUrl === 'https://example.test/go' && str_contains(tidy($mail), 'Hello!'), 'stock greeting, line and action keep working in order');

    $app_dir = $root.'/app';
    mkdir($app_dir.'/Notifications', 0777, true);
    file_put_contents($app_dir.'/Notifications/Good.php', "<?php\nuse Tey\\Kit\\Mail\\FluentMailMessage;\nclass Good { public function toMail() { return new FluentMailMessage; } }\n");
    mailCheck(MailConformance::offenders($app_dir) === [], 'a notification composed with FluentMailMessage conforms');
    file_put_contents($app_dir.'/Notifications/Stock.php', "<?php\nuse Illuminate\\Notifications\\Messages\\MailMessage;\nclass Stock { public function toMail() { return new MailMessage; } }\n");
    file_put_contents($app_dir.'/Notifications/Mailer.php', "<?php\nuse Illuminate\\Mail\\Mailable;\nclass Mailer extends Mailable {}\nMail::to('a@example.test')->send(new Mailer);\n");
    $offenders = MailConformance::offenders($app_dir);
    mailCheck(count($offenders) === 3 && in_array('Notifications/Stock.php composes mail without FluentMailMessage', $offenders, true) && in_array('Notifications/Mailer.php extends Mailable', $offenders, true) && in_array('Notifications/Mailer.php uses the Mail facade', $offenders, true), 'a stock MailMessage, a Mailable and the Mail facade are each flagged');

    echo "Fluent mail checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n");
    exit(1);
}
