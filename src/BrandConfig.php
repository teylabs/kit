<?php

namespace Tey\Kit;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Where each `kit.brand` value comes from. The app's own config/brand.php (Laravel loads it, and `config:cache` caches it, as `brand`) wins for every key it
 * names, an explicit null included; a key it leaves out falls back to its KIT_BRAND_* env value (deprecated, removed in 0.17), then to the kit's default.
 * The readers keep reading `config('kit.brand.*')`: the resolved values are written back there at register time.
 *
 * The colour keys are validated as they resolve: a #rgb or #rrggbb (a #rgb is widened to #rrggbb), or null for "use the theme" (or the kit default). An
 * empty KIT_BRAND_* colour reads as null, as it always has (it may be an unquoted `#hex` read as a comment, so it logs a hint once). An empty string in
 * config/brand.php and any other value are invalid: in local and testing that throws, naming the key (except under the lenient commands, see
 * LENIENT_COMMANDS); anywhere else it is logged and the key takes the kit's default, never an empty value.
 */
final class BrandConfig
{
    /** Each brand key (dotted under kit.brand) => its kit default, as config/kit.php has it with no env set. */
    public const DEFAULTS = [
        'identity' => null,
        'logomark' => '/logomark.svg',
        'icon' => null,
        'own' => [],
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

    /** The keys with no env variable (set only in config). */
    public const CONFIG_ONLY = ['own'];

    /** The colour keys, validated as they resolve. `header_rule` also takes `accent`. */
    public const COLOURS = ['mail.button', 'mail.button_dark', 'accent', 'accent_foreground', 'accent_strong', 'accent_dark', 'accent_dark_foreground', 'ink', 'header_rule',
        'background', 'card', 'border', 'muted', 'input', 'background_dark', 'card_dark', 'border_dark', 'muted_dark', 'input_dark', 'link', 'link_dark'];

    public const SOURCE_CONFIG = 'config';

    public const SOURCE_ENV = 'env (deprecated)';

    public const SOURCE_DEFAULT = 'default';

    /** @var array<string, true> Keys already logged in this process, so each is logged once, never per request. */
    private static array $logged = [];

    /** The env variable for a brand key: `mail.button` is KIT_BRAND_MAIL_BUTTON. Null for a config-only key. */
    public static function envName(string $key): ?string
    {
        return in_array($key, self::CONFIG_ONLY, true) ? null : 'KIT_BRAND_'.strtoupper(str_replace('.', '_', $key));
    }

    /**
     * The brand keys whose KIT_BRAND_* variable is set (even to an empty string), read by config/kit.php as it loads, so `config:cache` keeps the answer.
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
     * Resolves `kit.brand` in place: config/brand.php, then env, then the default; validates the colours; records each key's source in `kit.brand_sources`.
     * Returns the log lines due (deprecations and production fallbacks), each once per key per process.
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
        $envEmpty = array_values((array) $config->get('kit.brand_env_empty', []));
        $logs = [];
        foreach (self::DEFAULTS as $key => $default) {
            if (Arr::has($file, $key)) {
                $value = Arr::get($file, $key);
                $source = self::SOURCE_CONFIG;
            } elseif (in_array($key, $fromEnv, true)) {
                $value = Arr::get($brand, $key, $default);
                $source = self::SOURCE_ENV;
                if (self::once('env:'.$key)) {
                    $logs[] = ['level' => 'warning', 'message' => 'Deprecated: kit.brand.'.$key.' comes from '.self::envName($key).', and env brand values are removed in tey/kit 0.17. Run `php artisan kit:brand:migrate` to move them into config/brand.php.'];
                }
            } else {
                $value = Arr::has($brand, $key) ? Arr::get($brand, $key) : $default;
                $source = self::SOURCE_DEFAULT;
            }
            if (in_array($key, self::COLOURS, true) && $source === self::SOURCE_ENV && is_string($value) && trim($value) === '') {
                // Empty in env has always meant "use the theme": null, never a throw. It may be an unquoted #hex read as a comment, so say so once.
                $value = null;
                $envEmpty = array_values(array_unique([...$envEmpty, $key]));
                $invalid = array_values(array_diff($invalid, [$key]));
                if (self::once('env-empty:'.$key)) {
                    $logs[] = ['level' => 'warning', 'message' => 'kit.brand.'.$key.' ('.self::envName($key).') is empty in env, so it uses the theme. If you meant a colour, an unquoted #hex in .env is read as a comment: run `php artisan kit:brand:migrate`, which writes it to config/brand.php as null with a comment, then set the colour there.'];
                }
            }
            if (in_array($key, self::COLOURS, true) && $source !== self::SOURCE_DEFAULT) {
                $checked = self::colour($key, $value);
                if ($checked === false) {
                    $where = $source === self::SOURCE_CONFIG ? 'config/brand.php' : self::envName($key);
                    $problem = 'kit.brand.'.$key.' ('.$where.') is '.($value === '' ? 'empty' : var_export($value, true)).', which is not a #rgb or #rrggbb colour'
                        .'. Set a colour, or null to use the theme.';
                    if ($strict && in_array($environment, ['local', 'testing'], true)) {
                        throw new RuntimeException($problem);
                    }
                    if (self::once('invalid:'.$key)) {
                        $logs[] = ['level' => 'warning', 'message' => $problem.' Using the kit default ('.var_export($default, true).').'];
                    }
                    $invalid = array_values(array_unique([...$invalid, $key]));
                    $value = $default;
                } elseif ($key === 'accent' && $checked === null) {
                    // null: use the theme. kit:brand reads the theme's --primary for an empty accent, and the page head the neutral default, exactly as an empty accent did.
                    $value = '';
                } else {
                    $value = $checked;
                }
            }
            Arr::set($brand, $key, $value);
            $sources[$key] = $source;
        }
        $config->set('kit.brand', $brand);
        $config->set('kit.brand_sources', $sources);
        // The keys that resolved invalid and took the default, so kit:brand:migrate can flag them even from a cached config with no .env to read.
        $config->set('kit.brand_invalid', $invalid);
        // The colour keys that loaded empty from env and use the theme: kit:status notes them, and kit:brand:migrate flags them from a cached config too.
        $config->set('kit.brand_env_empty', $envEmpty);

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
        if ($key === 'header_rule' && $value === 'accent') {
            return 'accent';
        }
        if (preg_match('/\A#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])\z/', $value, $m) === 1) {
            return strtolower('#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3]);
        }

        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1 ? strtolower($value) : false;
    }

    /**
     * The console commands that boot leniently: an invalid colour warns and takes the default instead of throwing in local and testing. kit:brand:migrate is
     * the way out of an invalid env value, and kit:status reports it; both must start. kit:brand stays strict: it would bake the default into the icons.
     */
    public const LENIENT_COMMANDS = ['kit:brand:migrate', 'kit:status'];

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

    /** @return array<string, string> Each brand key => config, env (deprecated) or default, as resolved at boot. */
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
