<?php

namespace Tey\Kit;

/**
 * The platform's identity, read from `config('kit.brand')`: its name and logomark (the shell's mark, never a workspace's),
 * the lines of the web and mail footers, and the sender addresses. Nothing here is hard-coded to a product or a client.
 */
final class Brand
{
    /** The neutral default accent: zinc, no hue. */
    public const NEUTRAL_ACCENT = '#52525b';

    /** The theme's dark-mode page background, which dark-mode highlights are read against. */
    public const DARK_BACKGROUND = '#09090b';

    /** Config key => the placeholder values the kit and the starter ship, which a real brand replaces. */
    private const PLACEHOLDERS = [
        'name' => ['Laravel', 'Workspace starter', 'Product name', 'Example'],
        'logomark' => ['/logomark.svg'],
        'accent' => ['#52525b'],
        'hello_email' => ['hello@example.com'],
        'support_email' => ['support@example.com'],
        'privacy_email' => ['privacy@example.com'],
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

    /** Whether this is a private app: no legal name, postal address, Terms or Privacy in the footers. */
    public static function isPrivate(): bool
    {
        return (bool) self::get('private', false);
    }

    /** The product's name: APP_NAME. */
    public static function name(): string
    {
        return (string) config('app.name');
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
     * The web footer: the identity, the credit line (`{verb} by {org}`, then `, for …` when set; no © by default, and an app may add one as `© {year} {Product} · Built by Tey Labs Inc.`) and the legal links.
     *
     * @return array{identity: string, credit: string, links: list<array{label: string, url: string}>}
     */
    public static function webFooter(): array
    {
        return ['identity' => self::identity(), 'credit' => self::credit((string) self::get('credit_org')), 'links' => array_values(array_filter([
            ! self::isPrivate() && self::get('terms_url') ? ['label' => 'Terms', 'url' => (string) self::get('terms_url')] : null,
            ! self::isPrivate() && self::get('privacy_url') ? ['label' => 'Privacy', 'url' => (string) self::get('privacy_url')] : null,
            ! self::isPrivate() && self::get('privacy_email') ? ['label' => 'Privacy requests', 'url' => 'mailto:'.(string) self::get('privacy_email')] : null,
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

        $private = self::isPrivate();

        return ['identity' => self::identity(), 'sender' => implode(' · ', array_filter([$private ? '' : self::credit((string) self::get('legal_name')), $private ? '' : trim((string) self::get('postal_address')), trim((string) self::get('support_email'))], fn (string $part) => $part !== '')), 'manage' => ['label' => 'Manage notifications', 'url' => str_starts_with($manage, '/') ? url($manage) : $manage]];
    }

    /** The configuration `mail.brand` carries for the mail views, and the sender and reply-to the mailers use. */
    public static function mail(): array
    {
        $footer = self::mailFooter();
        $tokens = self::tokens();

        return ['identity' => $footer['identity'], 'sender' => $footer['sender'], 'credit' => (string) self::get('credit_verb').' by', 'legal_name' => self::isPrivate() ? '' : (string) self::get('legal_name'), 'address' => self::isPrivate() ? '' : (string) self::get('postal_address'), 'contact' => (string) self::get('support_email'), 'accent' => self::accent(), 'accent_foreground' => $tokens['accent_foreground'], 'accent_strong' => $tokens['accent_strong'], 'ink' => $tokens['ink'], 'mark' => self::markFile(), 'manage_url' => $footer['manage']['url']];
    }

    /** Applies the brand to the mailers: the sender is the product's name, replies go to the support address, and the mail views read `mail.brand`. */
    public static function applyToMail(): void
    {
        config([
            'mail.from' => ['address' => self::get('mail_from_address') ?: config('mail.from.address'), 'name' => self::name()],
            'mail.reply_to' => ['address' => self::get('support_email'), 'name' => self::name()],
            'mail.brand' => self::mail(),
        ]);
    }

    /** Whether the accent is still the neutral default (zinc), which `placeholders()` reports. */
    public static function accentIsNeutral(): bool
    {
        return self::accent() === self::NEUTRAL_ACCENT;
    }

    /** The accent as a literal hex colour. */
    public static function accent(): string
    {
        $accent = (string) self::get('accent');

        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $accent) === 1 ? strtolower($accent) : '#52525b';
    }

    /** The variants the local preview switch offers: name => its keys, from `kit.brand.presets`; only names and hex values that are safe to print in CSS. */
    public static function presets(): array
    {
        $presets = [];
        foreach ((array) self::get('presets', []) as $name => $values) {
            if (is_string($name) && preg_match('/\A[a-z0-9-]{1,32}\z/', $name) === 1 && is_array($values)) {
                $presets[$name] = $values;
            }
        }

        return $presets;
    }

    /** The preset the local switch has chosen for this session: only in a local environment, and only a configured one. */
    public static function activePreset(): ?string
    {
        if (! app()->environment('local') || ! app()->bound('request') || ! request()->hasSession()) {
            return null;
        }
        $name = request()->session()->get('kit.brand_preset');

        return is_string($name) && isset(self::presets()[$name]) ? $name : null;
    }

    /**
     * The colour tokens in force: the configured brand, with the active preset's keys over them. Every colour is a #rrggbb (anything else falls
     * back), and the companions default from the accent. The roles default from `style` (`fill`: accent buttons, the theme's own header, the strong
     * accent as the marker; `mono`: ink buttons and header, the accent as the marker), so a brand that sets none of them gets what it always got.
     * The optional colours (surfaces, links, the header rule) are null until set, and nothing is printed for a null.
     *
     * @return array{accent: string, accent_foreground: string, accent_strong: string, accent_dark: string, accent_dark_foreground: string, style: string, ink: string, button: string, button_dark: string, header: string, marker: string, header_rule: ?string, background: ?string, card: ?string, border: ?string, muted: ?string, background_dark: ?string, card_dark: ?string, border_dark: ?string, muted_dark: ?string, link: ?string, link_dark: ?string}
     */
    public static function tokens(?string $preset = null): array
    {
        $values = array_merge(config('kit.brand', []), $preset !== null ? (self::presets()[$preset] ?? []) : []);
        $hex = fn (string $key, string $default): string => is_string($values[$key] ?? null) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $values[$key]) === 1 ? strtolower($values[$key]) : $default;
        $optional = fn (string $key): ?string => is_string($values[$key] ?? null) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $values[$key]) === 1 ? strtolower($values[$key]) : null;
        $role = fn (string $key, array $allowed, string $default): string => in_array($values[$key] ?? null, $allowed, true) ? $values[$key] : $default;
        $accent = $hex('accent', self::NEUTRAL_ACCENT);
        $mono = ($values['style'] ?? 'fill') === 'mono';
        $rule = ($values['header_rule'] ?? null) === 'accent' ? 'accent' : $optional('header_rule');

        return [
            'accent' => $accent, 'accent_foreground' => $hex('accent_foreground', '#ffffff'), 'accent_strong' => $hex('accent_strong', $accent), 'accent_dark' => $hex('accent_dark', $accent), 'accent_dark_foreground' => $hex('accent_dark_foreground', '#09090b'),
            'style' => $mono ? 'mono' : 'fill', 'ink' => $hex('ink', '#09090b'),
            'button' => $role('button', ['accent', 'ink'], $mono ? 'ink' : 'accent'), 'button_dark' => $role('button_dark', ['accent', 'theme'], $mono ? 'theme' : 'accent'),
            'header' => $role('header', ['theme', 'ink'], $mono ? 'ink' : 'theme'), 'marker' => $role('marker', ['strong', 'accent'], $mono ? 'accent' : 'strong'),
            'header_rule' => $rule,
            'background' => $optional('background'), 'card' => $optional('card'), 'border' => $optional('border'), 'muted' => $optional('muted'),
            'background_dark' => $optional('background_dark'), 'card_dark' => $optional('card_dark'), 'border_dark' => $optional('border_dark'), 'muted_dark' => $optional('muted_dark'),
            'link' => $optional('link'), 'link_dark' => $optional('link_dark'),
        ];
    }

    /**
     * The CSS variables for a set of tokens (empty for the neutral default, which leaves the theme alone). The roles: the primary button is the accent or
     * `ink` (light) and the accent or the theme's own (dark); the header is the theme's or `ink`; the ring is the strong accent and the active marker the strong accent or the
     * accent itself. Then, only when set: the surfaces (`--background`, `--card`, `--border`, `--muted`), `--brand-link` and a rule under the header.
     */
    public static function css(array $t): string
    {
        $light = $dark = $bar = '';
        if (! self::rolesAreDefault($t)) {
            $light = ($t['button'] === 'accent' ? '--primary:'.$t['accent'].';--primary-foreground:'.$t['accent_foreground'] : '--primary:'.$t['ink'].';--primary-foreground:#ffffff')
                .';--ring:'.$t['accent_strong'].';--brand-marker:'.($t['marker'] === 'strong' ? $t['accent_strong'] : $t['accent']);
            $dark = ($t['button_dark'] === 'accent' ? '--primary:'.$t['accent_dark'].';--primary-foreground:'.$t['accent_dark_foreground'].';' : '').'--ring:'.$t['accent_dark'].';--brand-marker:'.$t['accent_dark'];
        }
        foreach (['background', 'card', 'border', 'muted'] as $surface) {
            $light .= $t[$surface] !== null ? ($light === '' ? '' : ';').'--'.$surface.':'.$t[$surface] : '';
            $dark .= $t[$surface.'_dark'] !== null ? ($dark === '' ? '' : ';').'--'.$surface.':'.$t[$surface.'_dark'] : '';
        }
        $light .= $t['link'] !== null ? ($light === '' ? '' : ';').'--brand-link:'.$t['link'] : '';
        $dark .= $t['link_dark'] !== null ? ($dark === '' ? '' : ';').'--brand-link:'.$t['link_dark'] : '';
        if ($t['header'] === 'ink') {
            $bar = '[data-shell-bar]{--background:'.$t['ink'].';--foreground:#ffffff;--muted-foreground:#a1a1aa;--accent:#27272a;--accent-foreground:#ffffff;--border:#27272a;--ring:'.$t['accent'].';--brand-marker:'.$t['accent'].'}';
        }
        $css = ($light === '' ? '' : ':root{'.$light.'}').($dark === '' ? '' : '.dark{'.$dark.'}').$bar;
        if ($t['header_rule'] !== null) {
            $css .= '[data-shell-bar]{border-bottom-width:2px;border-bottom-color:'.($t['header_rule'] === 'accent' ? $t['accent'] : $t['header_rule']).'}'
                .($t['header_rule'] === 'accent' && $t['accent_dark'] !== $t['accent'] ? '.dark [data-shell-bar]{border-bottom-color:'.$t['accent_dark'].'}' : '');
        }

        return $css;
    }

    /** Whether the accent and its roles are the neutral default, which leaves the theme's own primary, ring and marker alone. */
    private static function rolesAreDefault(array $t): bool
    {
        return $t['accent'] === self::NEUTRAL_ACCENT && $t['style'] === 'fill' && $t['button'] === 'accent' && $t['button_dark'] === 'accent' && $t['header'] === 'theme' && $t['marker'] === 'strong';
    }

    /** The WCAG 2 contrast ratio of two #rrggbb colours (1 to 21). */
    public static function contrast(string $foreground, string $background): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(fn (string $part): float => (static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4)(hexdec($part) / 255), str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        [$light, $dark] = [max($luminance($foreground), $luminance($background)), min($luminance($foreground), $luminance($background))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    /**
     * What fails 4.5:1 in a set of tokens: text on the accent (primary buttons), the accent as links and focus on the page (white unless the brand sets a background), and the same in dark mode
     * (against the dark page, `#09090b` unless set). With a configured card, link or dark surface the same colours are read against those too.
     * The accent itself is never text on the page.
     *
     * @return list<string>
     */
    public static function contrastProblems(array $t): array
    {
        if (self::css($t) === '') {
            return [];
        }
        $page = $t['background'] ?? '#ffffff';
        $darkPage = $t['background_dark'] ?? self::DARK_BACKGROUND;
        // The neutral default leaves the accent to the theme's own zinc, so only what the brand set (the links) is read against its surfaces.
        $accented = ! self::rolesAreDefault($t);
        $checks = [];
        if ($accented) {
            $checks[$t['background'] === null ? 'accent_strong on white' : 'accent_strong on the page background'] = [$t['accent_strong'], $page];
            if ($t['card'] !== null) {
                $checks['accent_strong on the card'] = [$t['accent_strong'], $t['card']];
            }
        }
        if ($t['link'] !== null) {
            $checks['link on the page background'] = [$t['link'], $page];
            if ($t['card'] !== null) {
                $checks['link on the card'] = [$t['link'], $t['card']];
            }
        }
        if ($accented && $t['button'] === 'accent') {
            $checks['accent_foreground on accent'] = [$t['accent_foreground'], $t['accent']];
        }
        if ($accented && $t['button'] === 'ink') {
            $checks['white on ink'] = ['#ffffff', $t['ink']];
        }
        if ($accented && $t['button_dark'] === 'accent') {
            $checks['accent_dark_foreground on accent_dark'] = [$t['accent_dark_foreground'], $t['accent_dark']];
        }
        if ($t['header'] === 'ink') {
            $checks['accent on ink (the header highlight)'] = [$t['accent'], $t['ink']];
        }
        if ($accented && ($t['style'] === 'mono' || $t['background_dark'] !== null)) {
            $checks['accent_dark on the dark background (focus and the marker in dark mode)'] = [$t['accent_dark'], $darkPage];
        }
        if ($accented && $t['card_dark'] !== null) {
            $checks['accent_dark on the dark card'] = [$t['accent_dark'], $t['card_dark']];
        }
        if ($t['link_dark'] !== null) {
            $checks['link_dark on the dark background'] = [$t['link_dark'], $darkPage];
            if ($t['card_dark'] !== null) {
                $checks['link_dark on the dark card'] = [$t['link_dark'], $t['card_dark']];
            }
        }
        $problems = [];
        foreach ($checks as $label => [$foreground, $background]) {
            if (self::contrast($foreground, $background) < 4.5) {
                $problems[] = $label.' is '.round(self::contrast($foreground, $background), 2).':1, below 4.5:1';
            }
        }

        return $problems;
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
            // A private app has no legal entity, address, Terms or Privacy to fill in.
            if (self::isPrivate() && in_array($key, ['legal_name', 'postal_address', 'terms_url', 'privacy_url', 'privacy_email'], true)) {
                continue;
            }
            $value = $key === 'name' ? self::name() : self::get($key);
            if ($value === null || $value === '' || in_array($value, $values, true)) {
                $left[] = 'brand.'.$key;
            }
        }

        return $left;
    }

    private static function credit(string $who): string
    {
        $for = (string) self::get('credit_for');

        // Nobody to credit: no "Built by" with nothing after it.
        if (trim($who) === '') {
            return '';
        }

        return trim(self::get('credit_verb').' by '.$who).($for === '' ? '' : ', for '.$for);
    }
}
