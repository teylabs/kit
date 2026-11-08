<?php

namespace Tey\Kit;

use Tey\Kit\Mail\MailUrl;

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

    /** @var array<string, mixed>|null the brand mailFor() is building, read before config while it runs (never config itself) */
    private static ?array $scope = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$scope !== null && array_key_exists($key, self::$scope)) {
            return self::$scope[$key];
        }

        return config('kit.brand.'.$key, $default);
    }

    /**
     * The `mail.brand` array for one message, from brand values in `kit.brand`'s shape (`name`, `identity`, `accent`, `accent_foreground`, `mail_lockup`,
     * `mail_mark`, `legal_name`, `support_email` and the rest) layered over the configured brand. Config is only read: a tenant's brand never becomes the
     * process's, so a later mail, or another worker job, gets the platform brand as before.
     *
     * @param  array<string, mixed>  $brand
     * @return array<string, mixed>
     */
    public static function mailFor(array $brand): array
    {
        $previous = self::$scope;
        self::$scope = [...($previous ?? []), ...$brand];
        try {
            return self::mail();
        } finally {
            self::$scope = $previous;
        }
    }

    /** Whether this is a private app: no legal name, postal address, Terms or Privacy in the footers. */
    public static function isPrivate(): bool
    {
        return (bool) self::get('private', false);
    }

    /** The product's name: APP_NAME. */
    public static function name(): string
    {
        return (string) (self::$scope['name'] ?? config('app.name'));
    }

    /** Line one of both footers: the product identity, the product's name unless set. */
    public static function identity(): string
    {
        return (string) (self::get('identity') ?: self::name());
    }

    /** Line one of both footers as printed: `© {year} {identity}`, or the identity alone with `kit.brand.copyright` off. The mail header keeps the plain identity. */
    public static function footerIdentity(): string
    {
        return filter_var(self::get('copyright', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false ? self::identity() : '© '.now()->year.' '.self::identity();
    }

    /** The full lockup image (`kit.brand.lockup` given as a path or URL rather than on/off), shown as it is in place of the mark and the wordmark. Null when unset. */
    public static function lockupImage(): ?string
    {
        $lockup = self::get('lockup');

        return is_string($lockup) && str_contains($lockup, '/') ? $lockup : null;
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
     * The web footer: the identity, the credit line (`{verb} by {org}`, then `, for …` when set). The identity carries `© {year}` unless `kit.brand.copyright` is off, so by default it reads `© {year} {Product} · Built by Tey Labs Inc.` and the legal links.
     *
     * @return array{identity: string, credit: string, links: list<array{label: string, url: string}>}
     */
    public static function webFooter(): array
    {
        return ['identity' => self::footerIdentity(), 'credit' => self::credit((string) self::get('credit_org')), 'links' => array_values(array_filter([
            ! self::isPrivate() && self::get('terms_url') ? ['label' => 'Terms', 'url' => (string) self::get('terms_url')] : null,
            ! self::isPrivate() && self::get('privacy_url') ? ['label' => 'Privacy', 'url' => (string) self::get('privacy_url')] : null,
            ! self::isPrivate() && self::get('privacy_email') ? ['label' => 'Privacy requests', 'url' => 'mailto:'.(string) self::get('privacy_email')] : null,
        ]))];
    }

    /**
     * The mail footer's three lines: the identity (with `© {year}`, as the web footer's); `{verb} by {legal name}[, for …] · {postal address} · {support email}`; and
     * the manage-notifications link.
     *
     * @return array{identity: string, sender: string, manage: array{label: string, url: string}}
     */
    public static function mailFooter(): array
    {
        $manage = (string) self::get('manage_url');

        $private = self::isPrivate();

        return ['identity' => self::footerIdentity(), 'sender' => implode(' · ', array_filter([$private ? '' : self::credit((string) self::get('legal_name')), $private ? '' : trim((string) self::get('postal_address')), trim((string) self::get('support_email'))], fn (string $part) => $part !== '')), 'manage' => ['label' => 'Manage notifications', 'url' => str_starts_with($manage, '/') ? MailUrl::to($manage) : $manage]];
    }

    /** The configuration `mail.brand` carries for the mail views, and the sender and reply-to the mailers use. */
    public static function mail(): array
    {
        $footer = self::mailFooter();
        $tokens = self::tokens();

        return ['name' => self::name(), 'identity' => self::identity(), 'footer_identity' => $footer['identity'], 'sender' => $footer['sender'], 'credit' => (string) self::get('credit_verb').' by', 'legal_name' => self::isPrivate() ? '' : (string) self::get('legal_name'), 'address' => self::isPrivate() ? '' : (string) self::get('postal_address'), 'contact' => (string) self::get('support_email'), 'accent' => self::accent(), 'accent_foreground' => $tokens['accent_foreground'], 'accent_strong' => $tokens['accent_strong'], 'ink' => $tokens['ink'], 'mark' => self::markFile(), 'lockup' => self::mailLockup(), 'manage_url' => $footer['manage']['url']];
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
     * @return array{accent: string, accent_foreground: string, accent_strong: string, accent_dark: string, accent_dark_foreground: string, style: string, ink: string, button: string, button_dark: string, header: string, marker: string, header_rule: ?string, background: ?string, card: ?string, border: ?string, muted: ?string, input: ?string, background_dark: ?string, card_dark: ?string, border_dark: ?string, muted_dark: ?string, input_dark: ?string, link: ?string, link_dark: ?string}
     */
    public static function tokens(?string $preset = null): array
    {
        $values = array_merge(config('kit.brand', []), self::$scope ?? [], $preset !== null ? (self::presets()[$preset] ?? []) : []);
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
            'background' => $optional('background'), 'card' => $optional('card'), 'border' => $optional('border'), 'muted' => $optional('muted'), 'input' => $optional('input'),
            'background_dark' => $optional('background_dark'), 'card_dark' => $optional('card_dark'), 'border_dark' => $optional('border_dark'), 'muted_dark' => $optional('muted_dark'), 'input_dark' => $optional('input_dark'),
            'link' => $optional('link'), 'link_dark' => $optional('link_dark'),
        ];
    }

    /**
     * The CSS variables for a set of tokens (empty for the neutral default, which leaves the theme alone). The roles: the primary button is the accent or
     * `ink` (light) and the accent or the theme's own (dark); the header is the theme's or `ink`; the ring is the strong accent and the active marker the strong accent or the
     * accent itself. Then, only when set: the surfaces (`--background`, `--card`, `--border`, `--muted`), a control's edge (`--input`), `--brand-link` and a rule under the header.
     */
    public static function css(array $t): string
    {
        $light = $dark = $bar = '';
        if (! self::rolesAreDefault($t)) {
            $light = ($t['button'] === 'accent' ? '--primary:'.$t['accent'].';--primary-foreground:'.$t['accent_foreground'] : '--primary:'.$t['ink'].';--primary-foreground:#ffffff')
                .';--ring:'.$t['accent_strong'].';--brand-marker:'.($t['marker'] === 'strong' ? $t['accent_strong'] : $t['accent']);
            $dark = ($t['button_dark'] === 'accent' ? '--primary:'.$t['accent_dark'].';--primary-foreground:'.$t['accent_dark_foreground'].';' : '').'--ring:'.$t['accent_dark'].';--brand-marker:'.$t['accent_dark'];
        }
        foreach (['background', 'card', 'border', 'input', 'muted'] as $surface) {
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
     * The accent itself is never text on the page. A configured control edge (`input`, `input_dark`) must hold 3:1 against its card (the page when no card is set).
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
        // A control's edge is not text: WCAG 1.4.11 asks 3:1 against the surface it sits on.
        $edges = [];
        if ($t['input'] !== null) {
            $edges[$t['card'] !== null ? 'input on the card' : ($t['background'] !== null ? 'input on the page background' : 'input on white')] = [$t['input'], $t['card'] ?? $page];
        }
        if ($t['input_dark'] !== null) {
            $edges[$t['card_dark'] !== null ? 'input_dark on the dark card' : 'input_dark on the dark background'] = [$t['input_dark'], $t['card_dark'] ?? $darkPage];
        }
        foreach ($edges as $label => [$foreground, $background]) {
            if (self::contrast($foreground, $background) < 3.0) {
                $problems[] = $label.' is '.round(self::contrast($foreground, $background), 2).':1, below 3:1';
            }
        }

        return $problems;
    }

    /** The mail lockup's file (`kit.brand.mail_lockup`): a path in `public/`, or an absolute file path. Null when unset, missing or not a PNG. */
    public static function mailLockupFile(): ?string
    {
        $lockup = self::get('mail_lockup');
        if (! is_string($lockup) || trim($lockup) === '') {
            return null;
        }
        $path = str_starts_with($lockup, '/') && is_file(public_path(ltrim($lockup, '/'))) ? public_path(ltrim($lockup, '/')) : $lockup;

        return is_file($path) && (string) file_get_contents($path, length: 8) === "\x89PNG\r\n\x1a\n" ? $path : null;
    }

    /**
     * The mail header's lockup: the file, sent inline (a CID attachment), at 220px wide with the height kept to the image's aspect ratio. A 2x or wider source stays sharp on
     * a high-density screen. Null with no usable lockup, and the header shows the product's name instead.
     *
     * @return array{file: string, width: int, height: int}|null
     */
    public static function mailLockup(): ?array
    {
        $file = self::mailLockupFile();
        $file = $file === null ? null : (self::plateMailLockup($file) ?? $file);
        $size = $file === null ? false : @getimagesize($file);
        if ($file === null || $size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }
        $width = min(220, (int) $size[0]);

        return ['file' => $file, 'width' => $width, 'height' => max(1, (int) round($width * $size[1] / $size[0]))];
    }

    /**
     * The lockup on its own light plate, baked into the PNG: a white rounded rectangle with a margin of a quarter of the lockup's height. A lockup drawn for a light
     * background is dark ink on transparency, and a dark-mode client paints the header dark under it. Apple Mail and Outlook could swap in a second image
     * (`prefers-color-scheme`, `[data-ogsc]`), but the Gmail apps honour neither: they recolour the message's backgrounds and text and never an image's pixels. A plate
     * inside the image is the one thing every client shows as drawn, so the ink reads on a light page, a dark page and a recoloured one. Written once per source
     * (keyed by its content) next to Laravel's cache, or the system temp directory. Null without GD or for a PNG GD can't read, and the lockup goes as it is.
     */
    public static function plateMailLockup(string $file): ?string
    {
        $hash = @sha1_file($file);
        if (! function_exists('imagecreatefrompng') || $hash === false) {
            return null;
        }
        try {
            $cache = storage_path('framework/cache');
        } catch (\Throwable) {
            $cache = '';
        }
        $cache = is_dir($cache) && is_writable($cache) ? $cache : sys_get_temp_dir();
        $plated = $cache.'/kit-mail-lockup-'.$hash.'.png';
        if (is_file($plated)) {
            return $plated;
        }
        $source = @imagecreatefrompng($file);
        if ($source === false) {
            return null;
        }
        [$width, $height] = [imagesx($source), imagesy($source)];
        $margin = max(4, (int) round($height / 4));
        [$plateWidth, $plateHeight] = [$width + 2 * $margin, $height + 2 * $margin];
        // About 6px at the 220px it shows at.
        $radius = max(2, min((int) round($plateWidth * 6 / 220), intdiv($plateHeight, 2)));
        $plate = imagecreatetruecolor($plateWidth, $plateHeight);
        imagealphablending($plate, false);
        imagesavealpha($plate, true);
        imagefill($plate, 0, 0, imagecolorallocatealpha($plate, 255, 255, 255, 127));
        $white = imagecolorallocatealpha($plate, 255, 255, 255, 0);
        imagefilledrectangle($plate, $radius, 0, $plateWidth - 1 - $radius, $plateHeight - 1, $white);
        imagefilledrectangle($plate, 0, $radius, $plateWidth - 1, $plateHeight - 1 - $radius, $white);
        foreach ([[$radius, $radius], [$plateWidth - 1 - $radius, $radius], [$radius, $plateHeight - 1 - $radius], [$plateWidth - 1 - $radius, $plateHeight - 1 - $radius]] as [$x, $y]) {
            imagefilledellipse($plate, $x, $y, 2 * $radius + 1, 2 * $radius + 1, $white);
        }
        imagealphablending($plate, true);
        imagecopy($plate, $source, $margin, $margin, 0, 0, $width, $height);
        $temporary = $plated.'.'.bin2hex(random_bytes(4));
        $written = @imagepng($plate, $temporary) && @rename($temporary, $plated);
        @unlink($temporary);

        return $written ? $plated : null;
    }

    /**
     * The mail button's fill and label, the same in light and dark. Light text on a fill that holds 4.5:1 against it: the accent with its foreground when
     * that is the lighter of the two, else the strong accent, else ink, with white. Dark text on a light fill is what dark-mode clients recolour badly (Gmail
     * darkens the fill and keeps or dims the text), while a dark fill under light text is left as it is, and reads on a light page and a dark one.
     *
     * @param  array<string, mixed>  $brand  `mail.brand`
     * @return array{background: string, foreground: string}
     */
    public static function mailButton(array $brand): array
    {
        $hex = fn (string $key, string $default): string => is_string($brand[$key] ?? null) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $brand[$key]) === 1 ? strtolower($brand[$key]) : $default;
        $accent = $hex('accent', self::NEUTRAL_ACCENT);
        $foreground = $hex('accent_foreground', '#ffffff');
        $lighter = self::contrast($foreground, '#000000') > self::contrast($accent, '#000000');
        if ($lighter && self::contrast($foreground, $accent) >= 4.5) {
            return ['background' => $accent, 'foreground' => $foreground];
        }
        foreach ([$hex('accent_strong', $accent), $hex('ink', '#09090b')] as $fill) {
            if (self::contrast('#ffffff', $fill) >= 4.5) {
                return ['background' => $fill, 'foreground' => '#ffffff'];
            }
        }

        return ['background' => '#09090b', 'foreground' => '#ffffff'];
    }

    /** The local PNG that mail embeds as an inline attachment, or null while `kit:brand` has not made it. */
    public static function markFile(): ?string
    {
        $mark = self::get('mail_mark');
        $file = is_string($mark) && $mark !== '' ? (str_starts_with($mark, '/') && is_file(public_path(ltrim($mark, '/'))) ? public_path(ltrim($mark, '/')) : $mark) : public_path('mail-mark.png');

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
