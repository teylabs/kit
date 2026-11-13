<?php

namespace Tey\Kit;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Where each `kit.brand` value comes from. The app's own config/brand.php (Laravel loads it, and `config:cache` caches it, as `brand`) wins for every key it
 * names, an explicit null included; a key it leaves out takes the kit's default. KIT_BRAND_* env values are not read since 0.17: one still set is
 * logged once at boot as ignored, so a leftover in .env, CI or a deploy is found (kit:brand:migrate moves it into config/brand.php).
 * The readers keep reading `config('kit.brand.*')`: the resolved values are written back there at register time.
 *
 * The colour keys are validated as they resolve: a #rgb or #rrggbb (a #rgb is widened to #rrggbb), or null for "use the theme" (or the kit default). An
 * empty string in config/brand.php and any other value are invalid: in local and testing that throws, naming the key (except under the lenient commands, see
 * LENIENT_COMMANDS); anywhere else it is logged and the key takes the kit's default, never an empty value. A key that resolves unset (null, or an empty
 * string) is left out of `kit.brand`, so `config('kit.brand.<key>')` is null and `config('kit.brand.<key>', $fallback)` returns the fallback.
 * `icon_colours` (`mono` or `full`) and `icon_padding` (0 up to 0.5) are validated the same way (CHOICES).
 */
final class BrandConfig
{
    /** Each brand key (dotted under kit.brand) => its kit default, as config/kit.php has it with no env set. */
    public const DEFAULTS = [
        'identity' => null,
        'logomark' => '/logomark.svg',
        'icon' => null,
        'own' => [],
        'icon_colours' => 'mono',
        'icon_padding' => null,
        'icon_background' => null,
        'logomark_dark' => null,
        'logomark_bar' => null,
        'wordmark' => null,
        'wordmark_dark' => null,
        'lockup' => false,
        'lockup_scope' => 'guest',
        'mail_lockup' => null,
        'mail.button' => null,
        'mail.button_dark' => null,
        'accent' => '#52525b',
        'accent_foreground' => '#ffffff',
        'accent_strong' => null,
        'accent_dark' => null,
        'accent_dark_foreground' => '#09090b',
        'style' => 'fill',
        'ink' => '#09090b',
        'button' => null,
        'button_dark' => null,
        'header' => null,
        'marker' => null,
        'header_rule' => null,
        'background' => null,
        'card' => null,
        'border' => null,
        'muted' => null,
        'input' => null,
        'background_dark' => null,
        'card_dark' => null,
        'border_dark' => null,
        'muted_dark' => null,
        'input_dark' => null,
        'link' => null,
        'link_dark' => null,
        'presets' => [],
        'hello_email' => 'hello@example.com',
        'support_email' => 'support@example.com',
        'privacy_email' => 'privacy@example.com',
        'mail_from_address' => 'updates@example.com',
        'legal_name' => 'Example Company',
        'postal_address' => '1 Example Street, Example City',
        'credit_verb' => 'Built',
        'credit_org' => 'Tey Labs Inc.',
        'copyright' => true,
        'credit_for' => null,
        'private' => false,
        'terms_url' => '/terms',
        'privacy_url' => '/privacy',
        'manage_url' => '/me',
        'font' => null,
    ];

    /** The keys that never had an env variable (set only in config). */
    public const CONFIG_ONLY = ['own', 'icon_colours', 'icon_padding', 'icon_background'];

    /** The colour keys, validated as they resolve. `header_rule` also takes `accent`, `icon_background` also takes `none`. */
    public const COLOURS = ['mail.button', 'mail.button_dark', 'accent', 'accent_foreground', 'accent_strong', 'accent_dark', 'accent_dark_foreground', 'ink', 'header_rule',
        'background', 'card', 'border', 'muted', 'input', 'background_dark', 'card_dark', 'border_dark', 'muted_dark', 'input_dark', 'link', 'link_dark', 'icon_background'];

    /** The other keys validated as they resolve, as the colours are: each => what it takes. */
    private const CHOICES = ['icon_colours' => "'mono' or 'full'", 'icon_padding' => 'a number from 0 up to (not including) 0.5'];

    public const SOURCE_CONFIG = 'config';

    public const SOURCE_DEFAULT = 'default';

    /** @var array<string, true> Keys already logged in this process, so each is logged once, never per request. */
    private static array $logged = [];

    /** The env variable a brand key used to read (removed in 0.17): `mail.button` is KIT_BRAND_MAIL_BUTTON. Null for a config-only key. */
    public static function envName(string $key): ?string
    {
        return in_array($key, self::CONFIG_ONLY, true) ? null : 'KIT_BRAND_'.strtoupper(str_replace('.', '_', $key));
    }

    /**
     * The brand keys whose KIT_BRAND_* variable is still set (even to an empty string), read by config/kit.php as it loads, so `config:cache` keeps the
     * answer. The values are ignored; only the names are reported.
     *
     * @return list<string>
     */
    public static function fromEnv(): array
    {
        $keys = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $name = self::envName($key);
            if ($name !== null && env($name) !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Resolves `kit.brand` in place: config/brand.php, then the default; validates the colours; records each key's source in `kit.brand_sources`.
     * Returns the log lines due (ignored KIT_BRAND_* variables and production fallbacks), each once per key per process.
     *
     * @return list<array{level: string, message: string}>
     */
    public static function resolve(Repository $config, string $environment, bool $strict = true): array
    {
        $brand = (array) $config->get('kit.brand', []);
        $file = $config->get('brand');
        $file = is_array($file) ? $file : [];
        $fromEnv = (array) $config->get('kit.brand_env', []);
        $sources = [];
        // Kept from a cached config, whose kit.brand is already resolved: a key that took the default there still counts as invalid.
        $invalid = array_values((array) $config->get('kit.brand_invalid', []));
        $logs = [];
        foreach (self::DEFAULTS as $key => $default) {
            if (Arr::has($file, $key)) {
                $value = Arr::get($file, $key);
                $source = self::SOURCE_CONFIG;
            } else {
                // Never the incoming kit.brand: a host's published pre-0.17 config/kit.php, or a config cache built before the upgrade, may hold env values there.
                // A value there that differs from the default is named once, so a brand kept in config/kit.php is moved rather than silently lost.
                if (Arr::has($brand, $key) && Arr::get($brand, $key) !== $default && ! in_array($key, $fromEnv, true) && self::once('kit:'.$key)) {
                    $logs[] = ['level' => 'warning', 'message' => 'kit.brand.'.$key.' is set outside config/brand.php (config/kit.php, or a config cache from before tey/kit 0.17) and is ignored: move it into config/brand.php, then run `php artisan config:cache` again if you cache config.'];
                }
                $value = $default;
                $source = self::SOURCE_DEFAULT;
            }
            if (in_array($key, $fromEnv, true) && self::once('env:'.$key)) {
                $logs[] = ['level' => 'warning', 'message' => self::envName($key).' is set but ignored: tey/kit 0.17 removed env brand values. Run `php artisan kit:brand:migrate` to move it into config/brand.php, then remove it from .env, CI and deploy settings.'];
            }
            if ((in_array($key, self::COLOURS, true) || isset(self::CHOICES[$key])) && $source !== self::SOURCE_DEFAULT) {
                $checked = isset(self::CHOICES[$key]) ? self::choice($key, $value) : self::colour($key, $value);
                if ($checked === false) {
                    $problem = 'kit.brand.'.$key.' (config/brand.php) is '.($value === '' ? 'empty' : var_export($value, true)).', which is not '.self::takes($key).'. '
                        .match (true) {
                            isset(self::CHOICES[$key]) => 'Set one, or null for the kit default.',
                            $key === 'icon_background' => 'Set one, or null for the accent.',
                            default => 'Set a colour, or null to use the theme.',
                        };
                    if ($strict && in_array($environment, ['local', 'testing'], true)) {
                        throw new RuntimeException($problem);
                    }
                    if (self::once('invalid:'.$key)) {
                        $logs[] = ['level' => 'warning', 'message' => $problem.' Using the kit default ('.var_export($default, true).').'];
                    }
                    $invalid = array_values(array_unique([...$invalid, $key]));
                    $value = $default;
                } else {
                    // null stays null ("use the theme"), so `config('kit.brand.accent', $fallback)` sees it as unset. kit:brand reads the theme's --primary for a
                    // null accent, and the page head the neutral default, exactly as an empty accent did.
                    $value = $checked;
                }
            }
            // An empty string is unset, so a `config('kit.brand.*', $fallback)` default applies. This covers a ''
            // an older config/brand.php or kit release left in a non-colour key (an empty colour in config/brand.php is still refused above).
            // An unset key is left out of kit.brand rather than set to null: Laravel's config() returns a present null as is, and only a missing key takes the default.
            if (is_string($value) && trim($value) === '') {
                $value = null;
            }
            if ($value === null) {
                Arr::forget($brand, $key);
            } else {
                Arr::set($brand, $key, $value);
            }
            $sources[$key] = $source;
        }
        $config->set('kit.brand', $brand);
        $config->set('kit.brand_sources', $sources);
        // The keys that resolved invalid and took the default, so kit:brand:migrate can flag them even from a cached config with no .env to read.
        $config->set('kit.brand_invalid', $invalid);

        return $logs;
    }

    /** Writes the due lines to the log; a log that cannot be written never stops the boot. */
    public static function log(LoggerInterface $logger, array $logs): void
    {
        foreach ($logs as $line) {
            rescue(fn () => $logger->log($line['level'], $line['message']), report: false);
        }
    }

    /**
     * A valid colour (#rrggbb, lowercased), null (unset: the theme), or false (invalid). `header_rule` also takes `accent`.
     */
    public static function colour(string $key, mixed $value): string|null|false
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return false;
        }
        $value = trim($value);
        if (($key === 'header_rule' && $value === 'accent') || ($key === 'icon_background' && $value === 'none')) {
            return $value;
        }
        if (preg_match('/\A#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])\z/', $value, $m) === 1) {
            return strtolower('#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3]);
        }

        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1 ? strtolower($value) : false;
    }

    /** What a validated key takes, for its warnings: a colour key a #rgb or #rrggbb colour (`icon_background` also `none`), a CHOICES key what that names. */
    public static function takes(string $key): string
    {
        return self::CHOICES[$key] ?? ($key === 'icon_background' ? "a #rgb or #rrggbb colour, or 'none'" : 'a #rgb or #rrggbb colour');
    }

    /** Whether config/brand.php may set the key to this value: a colour or CHOICES key is checked as it resolves (null included); any other key takes any value. */
    public static function valid(string $key, mixed $value): bool
    {
        return match (true) {
            isset(self::CHOICES[$key]) => self::choice($key, $value) !== false,
            in_array($key, self::COLOURS, true) => self::colour($key, $value) !== false,
            default => true,
        };
    }

    /** A CHOICES key's value as kit:brand reads it (`icon_padding` as a float), null (unset: the kit default), or false (invalid). */
    private static function choice(string $key, mixed $value): string|float|null|false
    {
        if ($value === null) {
            return null;
        }
        if ($key === 'icon_colours') {
            return in_array($value, ['mono', 'full'], true) ? $value : false;
        }

        return (is_int($value) || is_float($value)) && $value >= 0 && $value < 0.5 ? (float) $value : false;
    }

    /**
     * The console commands that boot leniently: an invalid colour warns and takes the default instead of throwing in local and testing. kit:brand:migrate is
     * the way out of an invalid env value, so it must start. kit:brand stays strict: it would bake the default into the icons.
     */
    public const LENIENT_COMMANDS = ['kit:brand:migrate'];

    /** Whether this artisan argv runs a lenient command: its first argument that is not an option, as Symfony reads the command name. */
    public static function lenientCommand(array $argv): bool
    {
        foreach (array_slice(array_values($argv), 1) as $arg) {
            if (is_string($arg) && $arg !== '' && ! str_starts_with($arg, '-')) {
                return in_array($arg, self::LENIENT_COMMANDS, true);
            }
        }

        return false;
    }

    /** @return array<string, string> Each brand key => config or default, as resolved at boot. */
    public static function sources(): array
    {
        return (array) config('kit.brand_sources', []);
    }

    /** Forgets what was logged (for checks that boot more than once in a process). */
    public static function forgetLogged(): void
    {
        self::$logged = [];
    }

    private static function once(string $id): bool
    {
        if (isset(self::$logged[$id])) {
            return false;
        }
        self::$logged[$id] = true;

        return true;
    }
}
