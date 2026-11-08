<?php

// Per-message mail identity (FluentMailMessage::as()): three mails sent in one process (the product's, tenant A's, tenant B's) through Laravel's real
// MailChannel and an array transport, on a synthetic app. Each carries its own sender, reply-to, header, button colour, footer and inline lockup, and
// config (mail.from, mail.reply_to, mail.brand, kit.brand) is the same after as before. No mail leaves the process.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Symfony\Component\Mime\Address;
use Tey\Kit\Brand;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\InlineBrandImages;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function identityCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function identityPng(string $path, int $width, int $height): void
{
    $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
    file_put_contents($path, "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND')));
}

$root = sys_get_temp_dir().'/kit-identity-'.bin2hex(random_bytes(8));
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
    $kit['brand'] = [...$kit['brand'], 'accent' => '#52525b', 'mail_from_address' => 'updates@product.test', 'support_email' => 'help@product.test', 'legal_name' => 'Product Ltd', 'mail_lockup' => $root.'/public/product.png'];
    $app->instance('config', new Repository(['app' => ['name' => 'Product', 'url' => 'https://product.test', 'locale' => 'en', 'fallback_locale' => 'en'], 'kit' => $kit, 'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'hello@example.com', 'name' => 'Example'], 'markdown' => ['theme' => 'default', 'paths' => []]]]));
    Facade::setFacadeApplication($app);
    $app->register(new FilesystemServiceProvider($app));
    $app->register(new TranslationServiceProvider($app));
    $app->register(new ViewServiceProvider($app));
    $app->register(new MailServiceProvider($app));
    $app->instance('request', Request::create('https://product.test/'));
    FluentMailMessage::appendMailPath(dirname(__DIR__).'/resources/views/mail');
    identityPng($root.'/public/product.png', 440, 80);
    identityPng($root.'/public/tenant-a.png', 440, 100);
    identityPng($root.'/public/tenant-b.png', 440, 120);
    Brand::applyToMail();
    $app['events']->listen(MessageSending::class, [InlineBrandImages::class, 'handle']);
    $before = json_encode([config('mail.from'), config('mail.reply_to'), config('mail.brand'), config('kit.brand'), config('app.name')]);

    $tenantA = ['name' => 'Alder Studio', 'accent' => '#0f766e', 'legal_name' => 'Alder Studio LLP', 'support_email' => 'desk@alder.test', 'mail_lockup' => $root.'/public/tenant-a.png'];
    $tenantB = ['name' => 'Birch Works', 'accent' => '#b91c1c', 'legal_name' => 'Birch Works Ltd', 'mail_lockup' => $root.'/public/tenant-b.png'];
    $mails = [
        'product' => fn () => (new FluentMailMessage)->subject('From the product')->paragraph('Body')->button('Open', 'https://product.test/a'),
        'A' => fn () => (new FluentMailMessage)->as($tenantA, new Address('notices@alder.test'))->subject('From A')->paragraph('Body')->button('Open', 'https://product.test/a'),
        'B' => fn () => (new FluentMailMessage)->as($tenantB, new Address('mail@birch.test', 'Birch Works mail'), new Address('owner@birch.test', 'Birch owner'))->subject('From B')->paragraph('Body')->button('Open', 'https://product.test/b'),
    ];
    $notifiable = new class
    {
        public function routeNotificationFor(string $channel): string
        {
            return 'reader@example.test';
        }
    };
    $channel = new MailChannel($app['mail.manager'], $app->make(Markdown::class));
    foreach ($mails as $build) {
        $channel->send($notifiable, new class($build) extends Notification
        {
            public function __construct(private Closure $build) {}

            public function toMail(object $notifiable): FluentMailMessage
            {
                return ($this->build)();
            }
        });
    }
    $sent = array_values(array_map(fn ($message) => $message->getOriginalMessage(), iterator_to_array($app['mail.manager']->mailer('array')->getSymfonyTransport()->messages())));
    identityCheck(count($sent) === 3, 'three mails were sent in one process');
    [$product, $a, $b] = $sent;
    $addresses = fn (array $list): array => array_map(fn (Address $address) => $address->getName().' <'.$address->getAddress().'>', $list);
    $html = fn ($message): string => quoted_printable_decode((string) $message->getHtmlBody());
    $lockup = fn ($message): ?string => ($parts = array_values(array_filter($message->getAttachments(), fn ($part) => $part->getFilename() === 'kit-lockup'))) ? $parts[0]->getBody() : null;

    identityCheck($addresses($product->getFrom()) === ['Product <updates@product.test>'] && $addresses($product->getReplyTo()) === ['Product <help@product.test>'], 'the product mail goes from the product, replies to its support address');
    identityCheck($addresses($a->getFrom()) === ['Alder Studio <notices@alder.test>'] && $addresses($a->getReplyTo()) === ['Alder Studio <desk@alder.test>'], 'tenant A goes from its own address under its name, and replies only to its support address (not the configured mail.reply_to too)');
    identityCheck($addresses($b->getFrom()) === ['Birch Works mail <mail@birch.test>'] && $addresses($b->getReplyTo()) === ['Birch owner <owner@birch.test>'], 'tenant B goes from the given sender, replies to the given address');

    identityCheck(str_contains($html($product), 'background-color: #52525b') && str_contains($html($product), 'Product Ltd') && str_contains($html($product), 'alt="Product"'), 'the product mail has the product accent, footer and lockup alt');
    identityCheck(str_contains($html($a), 'background-color: #0f766e') && str_contains($html($a), 'Alder Studio LLP') && str_contains($html($a), 'alt="Alder Studio"') && ! str_contains($html($a), 'Product Ltd') && ! str_contains($html($a), 'border-top: 10px solid #52525b'), 'tenant A has its accent, legal name and lockup alt, and none of the product\'s');
    identityCheck(str_contains($html($b), 'background-color: #b91c1c') && str_contains($html($b), 'Birch Works Ltd') && ! str_contains($html($b), 'Alder') && ! str_contains($html($b), 'Product Ltd'), 'tenant B has its own, and nothing of A\'s or the product\'s');
    identityCheck(str_contains((string) $a->getTextBody(), 'Alder Studio') && ! str_contains((string) $a->getTextBody(), 'Product Ltd'), 'the text part carries the tenant\'s footer too');

    identityCheck($lockup($product) === file_get_contents($root.'/public/product.png') && $lockup($a) === file_get_contents($root.'/public/tenant-a.png') && $lockup($b) === file_get_contents($root.'/public/tenant-b.png'), 'each mail embeds its own lockup inline, once');
    identityCheck(count(array_filter($a->getAttachments(), fn ($part) => $part->getFilename() === 'kit-lockup')) === 1 && str_contains($html($a), 'src="cid:kit-lockup"') && preg_match('~width="220" height="50"~', $html($a)) === 1 && preg_match('~width="220" height="60"~', $html($b)) === 1, 'each lockup is sized from its own file');

    identityCheck(json_encode([config('mail.from'), config('mail.reply_to'), config('mail.brand'), config('kit.brand'), config('app.name')]) === $before, 'mail.from, mail.reply_to, mail.brand, kit.brand and app.name are unchanged');
    $after = (string) (new FluentMailMessage)->subject('After')->paragraph('Body')->button('Open', 'https://product.test/c')->render();
    identityCheck(str_contains($after, 'background-color: #52525b') && str_contains($after, 'Product Ltd') && ! str_contains($after, 'Birch'), 'a mail built after them is the product\'s again');
    $sentBy = (new FluentMailMessage)->as($tenantA)->sentBy(new stdClass);
    identityCheck($sentBy->from === ['updates@product.test', 'Alder Studio'] && $sentBy->replyTo === [['desk@alder.test', 'Alder Studio']], 'with no sender of its own, a tenant mail goes from the platform address under the tenant\'s name, and sentBy() after as() keeps that identity');

    // as() called twice: the later brand wins everywhere, the inline lockup too, and it is embedded once.
    $transport = $app['mail.manager']->mailer('array')->getSymfonyTransport();
    $transport->flush();
    $channel->send($notifiable, new class(fn () => (new FluentMailMessage)->as($tenantA)->as($tenantB)->subject('Twice')->paragraph('Body')) extends Notification
    {
        public function __construct(private Closure $build) {}

        public function toMail(object $notifiable): FluentMailMessage
        {
            return ($this->build)();
        }
    });
    $twice = array_values(array_map(fn ($message) => $message->getOriginalMessage(), iterator_to_array($transport->messages())))[0];
    $lockups = array_values(array_filter($twice->getAttachments(), fn ($part) => $part->getFilename() === 'kit-lockup'));
    identityCheck(count($lockups) === 1 && $lockups[0]->getBody() === file_get_contents($root.'/public/tenant-b.png') && str_contains($html($twice), 'alt="Birch Works"'), 'as() called twice embeds only the later brand\'s lockup');

    // The workspace identity path (the settings feature's resolver): a workspace mail replies only to the workspace's reply-to, or to no one, never to
    // the product's support address (mail.reply_to) as well.
    $transport = $app['mail.manager']->mailer('array')->getSymfonyTransport();
    $transport->flush();
    try {
        foreach ([['address' => 'team@alder.test', 'name' => 'Alder team'], null] as $reply) {
            FluentMailMessage::resolveIdentityUsing(fn (?object $sender): array => ['from' => ['address' => 'notices@alder.test', 'name' => 'Alder Studio'], 'reply_to' => $reply]);
            $channel->send($notifiable, new class(fn () => (new FluentMailMessage)->subject('Workspace')->paragraph('Body')) extends Notification
            {
                public function __construct(private Closure $build) {}

                public function toMail(object $notifiable): FluentMailMessage
                {
                    return ($this->build)();
                }
            });
        }
    } finally {
        FluentMailMessage::resolveIdentityUsing(null);
    }
    [$withReply, $withoutReply] = array_values(array_map(fn ($message) => $message->getOriginalMessage(), iterator_to_array($transport->messages())));
    identityCheck($addresses($withReply->getFrom()) === ['Alder Studio <notices@alder.test>'] && $addresses($withReply->getReplyTo()) === ['Alder team <team@alder.test>'], 'a workspace mail replies only to the workspace\'s reply-to, not the product\'s support address too');
    identityCheck($withoutReply->getReplyTo() === [], 'a workspace with no reply-to gets no reply-to at all, not the product\'s');

    echo "Mail identity checks passed: three mails through Laravel's MailChannel and an array transport; no mail left the process, and no mail client rendered them.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
