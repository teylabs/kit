<?php

namespace Tey\Kit;

/**
 * The platform's identity, read from `config('kit.brand')`: its name and logomark (the shell's mark, never a workspace's),
 * the lines of the web and mail footers, and the sender addresses. Nothing here is hard-coded to a product or a client.
 */
final class Brand
{
    /** Config key => the placeholder values the kit and the starter ship, which a real brand replaces. */
    private const PLACEHOLDERS = [
        'name' => ['Product name', 'Laravel', 'Workspace starter', 'Example'],
        'logomark' => ['/logomark.svg'],
        'accent' => ['#4f46e5'],
        'hello_email' => ['hello@example.com'],
        'support_email' => ['support@example.com'],
        'mail_from_address' => ['updates@example.com'],
        'legal_name' => ['Example Company'],
        'postal_address' => ['1 Example Street, Example City'],
        'terms_url' => ['/terms'],
        'privacy_url' => ['/privacy'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return config('kit.brand.'.$key, $default);
    }

    /** The product's name: APP_NAME. */
    public static function name(): string
    {
        return (string) self::get('name');
    }

    /** Line one of both footers: the product identity, the product's name unless set. */
    public static function identity(): string
    {
        return (string) (self::get('identity') ?: self::name());
    }

    /** The logomark's source SVG: a path in `public/`, or an absolute file path. Null when there is none. */
    public static function logomarkPath(): ?string
    {
        $logomark = self::get('logomark');
        if (! is_string($logomark) || $logomark === '') {
            return null;
        }
        $path = str_starts_with($logomark, '/') && is_file(public_path($logomark)) ? public_path($logomark) : $logomark;

        return is_file($path) ? $path : null;
    }

    /**
     * The web footer: the identity, the credit line (`{verb} by {org}`, then `, for …` when set; never a ©) and the legal links.
     *
     * @return array{identity: string, credit: string, links: list<array{label: string, url: string}>}
     */
    public static function webFooter(): array
    {
        return ['identity' => self::identity(), 'credit' => self::credit((string) self::get('credit_org')), 'links' => array_values(array_filter([
            self::get('terms_url') ? ['label' => 'Terms', 'url' => (string) self::get('terms_url')] : null,
            self::get('privacy_url') ? ['label' => 'Privacy', 'url' => (string) self::get('privacy_url')] : null,
        ]))];
    }

    /**
     * The mail footer's three lines: the identity; `{verb} by {legal name}[, for …] · {postal address} · {support email}`; and
     * the manage-notifications link.
     *
     * @return array{identity: string, sender: string, manage: array{label: string, url: string}}
     */
    public static function mailFooter(): array
    {
        $manage = (string) self::get('manage_url');

        return ['identity' => self::identity(), 'sender' => implode(' · ', array_filter([self::credit((string) self::get('legal_name')), (string) self::get('postal_address'), (string) self::get('support_email')])), 'manage' => ['label' => 'Manage notifications', 'url' => str_starts_with($manage, '/') ? url($manage) : $manage]];
    }

    /** The configuration `mail.brand` carries for the mail views, and the sender and reply-to the mailers use. */
    public static function mail(): array
    {
        $footer = self::mailFooter();

        return ['identity' => $footer['identity'], 'credit' => (string) self::get('credit_verb').' by', 'legal_name' => (string) self::get('legal_name'), 'address' => (string) self::get('postal_address'), 'contact' => (string) self::get('support_email'), 'accent' => self::accent(), 'mark' => self::markFile(), 'manage_url' => $footer['manage']['url']];
    }

    /** The accent as a literal hex colour. */
    public static function accent(): string
    {
        $accent = (string) self::get('accent');

        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $accent) === 1 ? strtolower($accent) : '#4f46e5';
    }

    /** The local PNG that mail embeds as an inline attachment, or null while `kit:brand` has not made it. */
    public static function markFile(): ?string
    {
        $file = public_path('mail-mark.png');

        return is_file($file) ? $file : null;
    }

    /**
     * The brand keys still at their placeholder, by name only: a deploy check can warn on them, or block in production.
     *
     * @return list<string>
     */
    public static function placeholders(): array
    {
        $left = [];
        foreach (self::PLACEHOLDERS as $key => $values) {
            $value = self::get($key);
            if ($value === null || $value === '' || in_array($value, $values, true)) {
                $left[] = 'brand.'.$key;
            }
        }

        return $left;
    }

    private static function credit(string $who): string
    {
        $for = (string) self::get('credit_for');

        return trim(self::get('credit_verb').' by '.$who).($for === '' ? '' : ', for '.$for);
    }
}
