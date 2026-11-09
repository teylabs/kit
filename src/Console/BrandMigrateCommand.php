<?php

namespace Tey\Kit\Console;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Tey\Kit\BrandConfig;
use Throwable;

/**
 * Moves the brand out of env: writes config/brand.php with every key that resolves from KIT_BRAND_* today, at the value as loaded. A key whose variable is in .env
 * but loaded empty (an unquoted `#` read as a comment) or invalid is written as null with a comment, never a guessed value. It never edits .env: it prints the lines to delete.
 */
final class BrandMigrateCommand extends Command
{
    protected $signature = 'kit:brand:migrate {--force : Overwrite an existing config/brand.php}';

    protected $description = 'Write config/brand.php from the KIT_BRAND_* env values loaded today, and list the .env lines to delete (never edits .env)';

    public function handle(): int
    {
        $target = $this->laravel->configPath('brand.php');
        if (is_file($target) && ! $this->option('force')) {
            $this->error('config/brand.php already exists. Nothing written. Run again with --force to replace it (the keys it sets are kept as they are).');

            return self::FAILURE;
        }
        $envFile = $this->laravel->environmentFilePath();
        $lines = $this->envLines($envFile);
        $fromEnv = (array) config('kit.brand_env', []);
        $values = [];
        $flagged = [];
        $kept = [];
        $existing = is_array(config('brand')) ? config('brand') : [];
        foreach (array_keys(BrandConfig::DEFAULTS) as $key) {
            $name = (string) BrandConfig::envName($key);
            // A key config/brand.php already sets resolves from config: with --force it is carried over as it is, never replaced by its env value.
            if (Arr::has($existing, $key)) {
                $kept[$key] = Arr::get($existing, $key);

                continue;
            }
            if (! in_array($key, $fromEnv, true) && ! isset($lines[$name])) {
                continue;
            }
            $raw = env($name);
            $raw ??= isset($lines[$name]) ? $lines[$name]['value'] : null;
            // From a cached config with no .env to read: the cached resolved value, unless it loaded empty (the theme) or resolved invalid and took the default.
            if ($raw === null && in_array($key, $fromEnv, true) && ! in_array($key, [...(array) config('kit.brand_invalid', []), ...(array) config('kit.brand_env_empty', [])], true)) {
                $values[$key] = config('kit.brand.'.$key);

                continue;
            }
            if ($raw === '' || $raw === null) {
                $flagged[$key] = $name.' loaded empty'.(isset($lines[$name]) && str_starts_with(ltrim(substr($lines[$name]['line'], strpos($lines[$name]['line'], '=') + 1)), '#') ? ' (unquoted #?)' : '').'. null = use theme; set a value to override.';

                continue;
            }
            if (in_array($key, BrandConfig::COLOURS, true) && BrandConfig::colour($key, $raw) === false) {
                $flagged[$key] = $name.' is '.var_export($raw, true).', not a #rgb or #rrggbb colour. null = use theme; set a value to override.';

                continue;
            }
            // As loaded: the resolved value when env is its source now, else the raw value cast the way config/kit.php casts it.
            $values[$key] = (BrandConfig::sources()[$key] ?? null) === BrandConfig::SOURCE_ENV ? config('kit.brand.'.$key) : match ($key) {
                'lockup' => str_contains($raw, '/') ? $raw : filter_var($raw, FILTER_VALIDATE_BOOL),
                'copyright', 'private' => filter_var($raw, FILTER_VALIDATE_BOOL),
                'presets' => json_decode($raw, true) ?: [],
                default => in_array($key, BrandConfig::COLOURS, true) ? BrandConfig::colour($key, $raw) : $raw,
            };
        }
        if ($kept !== []) {
            $this->line('Kept from the existing config/brand.php: '.implode(', ', array_keys($kept)).'.');
        }
        if ($values === [] && $flagged === []) {
            $this->info('No KIT_BRAND_* values are set. Nothing written. Publish the commented stub with: php artisan vendor:publish --tag=kit-brand');

            return self::SUCCESS;
        }
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        file_put_contents($target, $this->render([...$kept, ...$values], $flagged));
        $this->info('Wrote config/brand.php: '.count($values).' key(s) from env'.($flagged === [] ? '.' : ', and '.count($flagged).' flagged key(s) written as null.'));
        foreach ($flagged as $key => $why) {
            $this->warn('FLAGGED kit.brand.'.$key.': '.$why);
        }
        $this->line('');
        $this->line('config/brand.php now wins over these. Delete them from '.basename($envFile).' (kit:brand:migrate never edits it):');
        $outside = [];
        foreach ([...array_keys($values), ...array_keys($flagged)] as $key) {
            $name = (string) BrandConfig::envName($key);
            if (isset($lines[$name])) {
                $this->line('  '.$lines[$name]['line']);
            } else {
                $outside[] = $name;
            }
        }
        if ($outside !== []) {
            $this->line('Set in the environment, not .env (remove them where they are set): '.implode(', ', $outside));
        }
        $this->line('Then run php artisan config:clear (or config:cache) and check php artisan kit:status.');

        return self::SUCCESS;
    }

    /** @return array<string, array{line: string, value: ?string}> Each KIT_BRAND_* variable in the .env file => its line, as written, and its parsed value. */
    private function envLines(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }
        $found = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) as $line) {
            if (preg_match('/\A\s*(?:export\s+)?(KIT_BRAND_[A-Z0-9_]+)\s*=/', $line, $m) === 1) {
                try {
                    $parsed = Dotenv::parse($line);
                } catch (Throwable) {
                    $parsed = [];
                }
                $found[$m[1]] = ['line' => rtrim($line), 'value' => $parsed[$m[1]] ?? null];
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $flagged
     */
    private function render(array $values, array $flagged): string
    {
        $tree = [];
        $notes = [];
        foreach (array_keys(BrandConfig::DEFAULTS) as $key) {
            if (array_key_exists($key, $values)) {
                Arr::set($tree, $key, $values[$key]);
            } elseif (isset($flagged[$key])) {
                Arr::set($tree, $key, null);
                $notes[$key] = $flagged[$key];
            }
        }
        $out = "<?php\n\n// The app's brand, read by tey/kit as `kit.brand`. Written by `php artisan kit:brand:migrate` from the KIT_BRAND_* values loaded at the time.\n"
            ."// A key here wins over its KIT_BRAND_* env value; a key left out uses env (deprecated, removed in tey/kit 0.16), then the kit's default.\n"
            ."// Colours are a #rgb or #rrggbb; null means \"use the theme\" (or the kit's default); an empty string is refused. The keys and their defaults:\n"
            ."// vendor/tey/kit/resources/stubs/brand.php.\nreturn [\n";

        return $out.$this->lines($tree, $notes, '', 1)."];\n";
    }

    /** @param array<string, string> $notes */
    private function lines(array $tree, array $notes, string $prefix, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $out = '';
        foreach ($tree as $key => $value) {
            $dotted = $prefix.$key;
            if (is_array($value) && ! in_array($dotted, ['own', 'presets'], true)) {
                $out .= $pad.var_export($key, true)." => [\n".$this->lines($value, $notes, $dotted.'.', $depth + 1).$pad."],\n";

                continue;
            }
            if (isset($notes[$dotted])) {
                $out .= $pad.'// '.$notes[$dotted]."\n";
            }
            $out .= $pad.var_export($key, true).' => '.$this->export($value, $depth).",\n";
        }

        return $out;
    }

    private function export(mixed $value, int $depth = 1): string
    {
        if (! is_array($value)) {
            return $value === null ? 'null' : var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }
        $pad = str_repeat('    ', $depth + 1);
        $list = array_is_list($value);
        $out = "[\n";
        foreach ($value as $k => $v) {
            $out .= $pad.($list ? '' : var_export($k, true).' => ').$this->export($v, $depth + 1).",\n";
        }

        return $out.str_repeat('    ', $depth).']';
    }
}
