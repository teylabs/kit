<?php

// FluentMailMessage escapes user text exactly once: every builder method, in the branded and unbranded layouts, in the HTML part (one entity decode gives the
// text back, no live tag) and the text part (the text verbatim, no entity, no markdown backslash). Synthetic app, no provider of the kit's, no mail sent.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Tey\Kit\Mail\FluentMailMessage;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

$failures = 0;
function escapeCheck(bool $ok, string $message): void
{
    global $failures;
    if (! $ok) {
        $failures++;
        echo 'FAIL '.$message."\n";

        return;
    }
    echo 'PASS '.$message."\n";
}

$root = sys_get_temp_dir().'/kit-mail-escape-'.bin2hex(random_bytes(8));
mkdir($root.'/storage/framework/views', 0777, true);
file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
register_shutdown_function(function () use ($root): void {
    exec('rm -rf '.escapeshellarg($root));
});

$app = new Application($root);
Container::setInstance($app);
$app->instance('config', new Repository(['app' => ['name' => 'Synthetic', 'url' => 'https://example.test', 'locale' => 'en', 'fallback_locale' => 'en'], 'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'], 'mail' => ['markdown' => ['theme' => 'default', 'paths' => []]]]));
Facade::setFacadeApplication($app);
foreach ([FilesystemServiceProvider::class, EventServiceProvider::class, TranslationServiceProvider::class, ViewServiceProvider::class, MailServiceProvider::class] as $provider) {
    $app->register(new $provider($app));
}

// Each value goes through every builder method that takes user text.
$values = [
    'apostrophe' => "Ada's talk",
    'double quotes' => 'The "final" cut',
    'ampersand' => 'Q&A & more',
    'angle brackets' => 'a < b > c',
    'script tag' => '<script>alert(1)</script>',
    'bold tag' => '<b>loud</b>',
    'markdown emphasis' => '*x* and _y_',
    'markdown link' => '[a](b)',
];
$builders = [
    'headline' => fn (FluentMailMessage $m, string $v) => $m->headline($v),
    'paragraph' => fn (FluentMailMessage $m, string $v) => $m->paragraph($v),
    'facts' => fn (FluentMailMessage $m, string $v) => $m->facts(['Title' => $v]),
    'table' => fn (FluentMailMessage $m, string $v) => $m->table(['Item'], [[$v]]),
    'panel' => fn (FluentMailMessage $m, string $v) => $m->panel($v),
    'callout' => fn (FluentMailMessage $m, string $v) => $m->callout('Note', $v),
    'note' => fn (FluentMailMessage $m, string $v) => $m->note($v),
    'bullets' => fn (FluentMailMessage $m, string $v) => $m->bullets([$v]),
    'quote' => fn (FluentMailMessage $m, string $v) => $m->quote($v),
    'section' => fn (FluentMailMessage $m, string $v) => $m->section($v, fn () => null),
    'button label' => fn (FluentMailMessage $m, string $v) => $m->button($v, 'https://example.test/go'),
    'secondary link label' => fn (FluentMailMessage $m, string $v) => $m->button($v, 'https://example.test/go', 'secondary'),
];

// The visible text of the HTML body: tags dropped, entities decoded once.
$visible = fn (string $html): string => html_entity_decode(strip_tags((string) preg_replace('/<(style|head)\b.*?<\/\1>/is', '', $html)), ENT_QUOTES | ENT_HTML5);
$live = fn (string $html): bool => (bool) preg_match('/<script\b|<b>loud/i', $html);

foreach (['unbranded' => null, 'branded' => dirname(__DIR__).'/resources/views/mail'] as $layout => $kitMail) {
    config(['mail.markdown.paths' => []]);
    if ($kitMail !== null) {
        config(['mail.brand' => ['identity' => "O'Brien & Co", 'sender' => '', 'manage_url' => 'https://example.test/n', 'accent' => '#52525b', 'accent_foreground' => '#fefefe']]);
        FluentMailMessage::appendMailPath($kitMail);
    }
    FluentMailMessage::register();
    foreach ($builders as $builder => $build) {
        foreach ($values as $name => $value) {
            $mail = $build((new FluentMailMessage)->subject('Hi'), $value);
            $html = (string) $mail->render();
            $text = (string) $app->make(Markdown::class)->renderText($mail->markdown, $mail->data());
            $shown = $visible($html);
            escapeCheck(str_contains($shown, $value) && ! $live($html), "{$layout} html, {$builder}, {$name}: shown once-escaped as text".(str_contains($shown, $value) ? '' : ' (got: '.trim(substr((string) preg_replace('/\s+/', ' ', $shown), 0, 160)).')'));
            escapeCheck(str_contains($text, $value), "{$layout} text, {$builder}, {$name}: verbatim".(str_contains($text, $value) ? '' : ' (got: '.trim(substr((string) preg_replace('/\s+/', ' ', $text), 0, 160)).')'));
        }
    }
    if ($kitMail !== null) {
        $html = (string) (new FluentMailMessage)->subject('Hi')->paragraph('x')->render();
        escapeCheck(str_contains($visible($html), "O'Brien & Co") && ! str_contains($html, '&amp;#039;') && ! str_contains($html, '&amp;amp;'), 'branded footer: the brand identity is escaped once');
    }
}

echo $failures === 0 ? "Mail escaping: every builder, both layouts, html and text parts. No mail client or real send.\n" : "{$failures} mail escaping checks failed.\n";
exit($failures === 0 ? 0 : 1);
