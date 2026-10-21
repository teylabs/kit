<?php

use Illuminate\Support\Facades\Facade;
use Tey\Kit\Brand;

require dirname(__DIR__).'/vendor/autoload.php';

/** The brand's variant tokens, its CSS, the local-only switch's bounds, and the 4.5:1 contrast guard. No client values: synthetic colours only. */
function variantCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$app = new Illuminate\Foundation\Application(dirname(__DIR__));
Illuminate\Container\Container::setInstance($app);
$app->instance('config', new Illuminate\Config\Repository(['kit' => ['brand' => ['accent' => '#52525b', 'presets' => ['fill-one' => ['accent' => '#2563eb', 'accent_foreground' => '#ffffff', 'accent_strong' => '#1d4ed8', 'accent_dark' => '#60a5fa', 'accent_dark_foreground' => '#09090b'], 'mono-one' => ['accent' => '#16a34a', 'accent_strong' => '#15803d', 'accent_dark' => '#4ade80', 'style' => 'mono', 'ink' => '#050505'], 'Bad Name' => ['accent' => '#ff0000'], 'x' => 'not an array']]]]));
Facade::setFacadeApplication($app);

variantCheck(abs(Brand::contrast('#000000', '#ffffff') - 21.0) < 0.01 && abs(Brand::contrast('#ffffff', '#ffffff') - 1.0) < 0.01, 'contrast is the WCAG ratio (21:1 black on white, 1:1 alike)');
variantCheck(array_keys(Brand::presets()) === ['fill-one', 'mono-one'], 'only well-named presets that are arrays are offered');
$neutral = Brand::tokens();
variantCheck($neutral['accent'] === '#52525b' && $neutral['style'] === 'fill' && Brand::css($neutral) === '', 'the neutral default emits no overriding CSS');
variantCheck(Brand::contrastProblems($neutral) === [], 'the neutral default passes 4.5:1');
$fill = Brand::tokens('fill-one');
$css = Brand::css($fill);
variantCheck(str_contains($css, ':root{--primary:#2563eb;--primary-foreground:#ffffff;--ring:#1d4ed8') && str_contains($css, '.dark{--primary:#60a5fa;--primary-foreground:#09090b'), 'fill: the accent fills primary, with the strong accent for focus and the dark accent in dark mode');
$mono = Brand::tokens('mono-one');
$css = Brand::css($mono);
variantCheck($mono['style'] === 'mono' && str_contains($css, '--primary:#050505') && str_contains($css, '[data-shell-bar]{--background:#050505') && str_contains($css, '--brand-marker:#16a34a'), 'mono: ink header and primary, the accent only a highlight');
variantCheck(Brand::contrastProblems($fill) === [] && Brand::contrastProblems($mono) === [], 'the synthetic variants pass 4.5:1');
$weak = Brand::tokens('fill-one');
$weak['accent_strong'] = '#93c5fd';
$weak['accent_foreground'] = '#bfdbfe';
variantCheck(count(Brand::contrastProblems($weak)) === 2, 'a weak accent-strong on white, or accent-foreground on the accent, fails the guard');
$dim = Brand::tokens('mono-one');
$dim['accent_dark'] = '#14532d';
variantCheck(Brand::contrastProblems($dim) !== [] && str_contains(implode(' ', Brand::contrastProblems($dim)), 'accent_dark on the dark background'), 'a mono preset with a dim accent_dark fails against the dark background');
$unsafe = Brand::tokens('fill-one');
config(['kit.brand.presets.fill-one.accent' => 'red;}body{display:none']);
variantCheck(Brand::tokens('fill-one')['accent'] === '#52525b', 'a colour that is not #rrggbb is never printed into CSS');

// The footers: every part optional, no dangling separator, and a private mode that leaves out the legal parts.
$footerBrand = ['name' => 'Product', 'credit_verb' => 'Built', 'credit_org' => 'Studio', 'credit_for' => null, 'legal_name' => 'Legal Co', 'postal_address' => '1 Street', 'support_email' => 'help@example.test', 'terms_url' => '/terms', 'privacy_url' => '/privacy', 'manage_url' => 'https://example.test/me', 'logomark' => '/logomark.svg', 'accent' => '#52525b', 'hello_email' => 'hello@example.test', 'mail_from_address' => 'u@example.test', 'private' => false];
$withBrand = function (array $over) use ($footerBrand): void {
    config(['kit.brand' => array_merge($footerBrand, $over), 'app.name' => 'Product']);
};
$noDangling = fn (string $text): bool => ! preg_match('/(^|\s)·(\s·|\s*$)|^\s*·|Built by\s*($|·|,)|,\s*for\s*$/u', $text) && ! str_contains($text, '·  ·');

$withBrand([]);
variantCheck(Brand::mailFooter()['sender'] === 'Built by Legal Co · 1 Street · help@example.test', 'the mail footer, all set: the credit, the address and the contact');
variantCheck(array_column(Brand::webFooter()['links'], 'label') === ['Terms', 'Privacy'] && Brand::webFooter()['credit'] === 'Built by Studio', 'the web footer, all set: the credit and both links');
variantCheck(Brand::mail()['sender'] === Brand::mailFooter()['sender'], 'the mail views get the same line');

foreach (['legal_name', 'postal_address', 'support_email'] as $key) {
    $withBrand([$key => '']);
    $sender = Brand::mailFooter()['sender'];
    variantCheck($noDangling($sender) && $sender !== '' && ! str_contains($sender, $footerBrand[$key]), "the mail footer with no {$key} leaves it out cleanly: {$sender}");
}
$withBrand(['legal_name' => '', 'postal_address' => '', 'support_email' => '']);
variantCheck(Brand::mailFooter()['sender'] === '', 'a mail footer with nothing to say has no line at all');
$withBrand(['terms_url' => '']);
variantCheck(array_column(Brand::webFooter()['links'], 'label') === ['Privacy'], 'the web footer with no Terms link has no empty link');
$withBrand(['terms_url' => '', 'privacy_url' => '']);
variantCheck(Brand::webFooter()['links'] === [], 'the web footer with neither link has none');
$withBrand(['credit_org' => '']);
variantCheck(Brand::webFooter()['credit'] === '' && $noDangling(Brand::webFooter()['credit']), 'a web credit with no organisation is not "Built by" and nothing');
$withBrand(['credit_for' => 'Client']);
variantCheck(Brand::webFooter()['credit'] === 'Built by Studio, for Client' && Brand::mailFooter()['sender'] === 'Built by Legal Co, for Client · 1 Street · help@example.test', 'the "for" client goes after the credit in both');

$withBrand(['private' => true]);
variantCheck(Brand::isPrivate() && Brand::webFooter()['links'] === [] && Brand::webFooter()['credit'] === 'Built by Studio', 'private mode: the web footer has no Terms or Privacy, and keeps the credit');
variantCheck(Brand::mailFooter()['sender'] === 'help@example.test' && Brand::mail()['legal_name'] === '' && Brand::mail()['address'] === '', 'private mode: the mail footer has no legal name or address, only the contact');
$withBrand(['private' => true, 'support_email' => '']);
variantCheck(Brand::mailFooter()['sender'] === '', 'private mode with no contact either: no line');

// Placeholders: example values are reported as unset; private mode does not ask for the legal parts, and nothing else changes.
$withBrand(['legal_name' => 'Example Company', 'postal_address' => '1 Example Street, Example City', 'terms_url' => '/terms', 'privacy_url' => '/privacy', 'support_email' => 'support@example.com', 'private' => false]);
$open = Brand::placeholders();
variantCheck(['brand.legal_name', 'brand.postal_address', 'brand.terms_url', 'brand.privacy_url', 'brand.support_email'] === array_values(array_intersect(['brand.legal_name', 'brand.postal_address', 'brand.terms_url', 'brand.privacy_url', 'brand.support_email'], $open)), 'placeholders report the example legal parts as unset');
$withBrand(['legal_name' => 'Example Company', 'postal_address' => '1 Example Street, Example City', 'terms_url' => '/terms', 'privacy_url' => '/privacy', 'support_email' => 'support@example.com', 'private' => true]);
$private = Brand::placeholders();
variantCheck(array_values(array_intersect(['brand.legal_name', 'brand.postal_address', 'brand.terms_url', 'brand.privacy_url', 'brand.privacy_email'], $private)) === [] && in_array('brand.support_email', $private, true), 'private mode: the legal parts are not reported as unset, the rest still is');
variantCheck(array_values(array_diff($open, ['brand.legal_name', 'brand.postal_address', 'brand.terms_url', 'brand.privacy_url', 'brand.privacy_email'])) === $private, 'private mode changes only those five placeholders');
