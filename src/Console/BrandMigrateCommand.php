<?php

namespace Tey\Kit\Console;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Tey\Kit\BrandConfig;
use Throwable;

/**
 * Moves the brand out of env: writes config/brand.php with every KIT_BRAND_* value still set, cast as config/kit.php cast it before 0.17 (tey/kit no longer
 * reads them: config/brand.php is the only source). It reads the process environment and the .env file, so run it before `config:cache`. A key whose variable is in .env
 * but loaded empty (an unquoted `#` read as a comment) or invalid is written as null with a comment, never a guessed value. It never edits .env: it prints the lines to delete.
 * A key with no KIT_BRAND_* variable takes its value from the `brand` array of the app's own config/kit.php, which is what `kit.brand` merged to before 0.17, when
 * that differs from the kit's default. It reads only this environment: a value set only in another one (production's) is copied by hand, and it says so.
 */
final class BrandMigrateCommand extends Command
{
    protected $signature = 'kit:brand:migrate {--force : Overwrite an existing config/brand.php}';

    protected $description = 'Write config/brand.php from the KIT_BRAND_* env values loaded today and the brand in config/kit.php, and list the .env lines to delete (never edits .env)';

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
        $normalised = false;
        $existing = is_array(config('brand')) ? config('brand') : [];
        // The app's own config/kit.php, read from the file: by now `kit.brand` is resolved, and with a config cache Laravel never loaded the file.
        $kitFile = $this->laravel->configPath('kit.php');
        try {
            $kit = is_file($kitFile) ? (static fn (string $path): mixed => require $path)($kitFile) : [];
        } catch (Throwable $e) {
            $this->error('config/kit.php could not be read ('.$e->getMessage().'). Nothing written: fix it and run again, so its brand is not lost.');

            return self::FAILURE;
        }
        $kitBrand = is_array($kit) && is_array($kit['brand'] ?? null) ? $kit['brand'] : [];
        // Ignored since 0.17 whatever it holds, so it is named for deletion even when every key in it is the kit's default and nothing is written from it.
        $deleteKitBrand = $kitBrand === [] ? null : 'tey/kit ignores kit.brand in config/kit.php since 0.17. Delete its \'brand\' array';
        $fromKit = [];
        foreach (array_keys(BrandConfig::DEFAULTS) as $key) {
            $name = (string) BrandConfig::envName($key);
            // A key config/brand.php already sets resolves from config: with --force it is carried over as it is, never replaced by its env value.
            if (Arr::has($existing, $key)) {
                $kept[$key] = Arr::get($existing, $key);
                // An empty string an older file left in a non-colour key is written back as null (unset), so config() defaults apply. An empty colour stays
                // as it is: boots refuse it, and turning it into "use the theme" here would hide that.
                if (! in_array($key, BrandConfig::COLOURS, true) && is_string($kept[$key]) && trim($kept[$key]) === '') {
                    $kept[$key] = null;
                    $normalised = true;
                }

                continue;
            }
            // The process environment too: with a cached config, kit.brand_env is the snapshot from when the cache was built, and a deploy may set a variable
            // only for this run. A key with a variable anywhere comes from env, never from config/kit.php: present, not env()'s value, which reads `null` as null.
            if (! in_array($key, $fromEnv, true) && ! isset($lines[$name]) && ($name === '' || (getenv($name) === false && ! isset($_ENV[$name]) && ! isset($_SERVER[$name])))) {
                // No variable: the value config/kit.php gave kit.brand before 0.17, already cast by that file, when it differs from the default. An empty
                // string was unset then, so it is null now; an invalid colour or icon choice is flagged as null, never written (the next boot would refuse it).
                if (! Arr::has($kitBrand, $key)) {
                    continue;
                }
                $value = Arr::get($kitBrand, $key);
                $value = is_string($value) && trim($value) === '' ? null : $value;
                if (! BrandConfig::valid($key, $value)) {
                    $flagged[$key] = 'kit.brand.'.$key.' in config/kit.php is '.var_export($value, true).', not '.BrandConfig::takes($key).'. '
                        .match (true) {
                            ! in_array($key, BrandConfig::COLOURS, true) => 'null = the kit default',
                            $key === 'icon_background' => 'null = the accent',
                            default => 'null = use theme',
                        }.'; set a value to override.';
                    $fromKit[] = $key;

                    continue;
                }
                $value = in_array($key, BrandConfig::COLOURS, true) ? BrandConfig::colour($key, $value) : $value;
                if ($value !== BrandConfig::DEFAULTS[$key]) {
                    $values[$key] = $value;
                    $fromKit[] = $key;
                }

                continue;
            }
            $raw = env($name);
            $raw ??= isset($lines[$name]) ? $lines[$name]['value'] : null;
            if ($raw === '' || $raw === null) {
                $flagged[$key] = $name.' loaded empty'.(isset($lines[$name]) && str_starts_with(ltrim(substr($lines[$name]['line'], strpos($lines[$name]['line'], '=') + 1)), '#') ? ' (unquoted #?)' : '').'. null = use theme; set a value to override.';

                continue;
            }
            if (in_array($key, BrandConfig::COLOURS, true) && BrandConfig::colour($key, $raw) === false) {
                $flagged[$key] = $name.' is '.var_export($raw, true).', not a #rgb or #rrggbb colour. null = use theme; set a value to override.';

                continue;
            }
            // The raw value cast the way config/kit.php cast it before 0.17.
            $values[$key] = match ($key) {
                'lockup' => str_contains($raw, '/') ? $raw : filter_var($raw, FILTER_VALIDATE_BOOL),
                'copyright', 'private' => filter_var($raw, FILTER_VALIDATE_BOOL),
                'presets' => json_decode($raw, true) ?: [],
                default => in_array($key, BrandConfig::COLOURS, true) ? BrandConfig::colour($key, $raw) : $raw,
            };
        }
        if ($kept !== []) {
            $this->line('Kept from the existing config/brand.php: '.implode(', ', array_keys($kept)).'.');
        }
        $elsewhere = 'Only this environment was read (its environment, '.basename($envFile).' and config/kit.php). A brand value set only in another environment (production\'s environment, CI or deploy settings) is not seen here: copy it into config/brand.php by hand.';
        if ($values === [] && $flagged === [] && ! $normalised) {
            $this->info('No KIT_BRAND_* values are set and config/kit.php sets no brand'.($deleteKitBrand === null ? '' : ' other than the kit\'s defaults').'. Nothing written. Publish the commented stub with: php artisan vendor:publish --tag=kit-brand');
            if ($deleteKitBrand !== null) {
                $this->line($deleteKitBrand.'.');
            }
            $this->warn($elsewhere);

            return self::SUCCESS;
        }
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        file_put_contents($target, $this->render([...$kept, ...$values], $flagged));
        $fromKitCount = count(array_intersect(array_keys($values), $fromKit));
        $this->info('Wrote config/brand.php: '.(count($values) - $fromKitCount).' key(s) from env, '.$fromKitCount.' from config/kit.php'.($flagged === [] ? '.' : ', and '.count($flagged).' flagged key(s) written as null.'));
        foreach ($flagged as $key => $why) {
            $this->warn('FLAGGED kit.brand.'.$key.': '.$why);
        }
        $this->line('');
        $envKeys = array_diff([...array_keys($values), ...array_keys($flagged)], $fromKit);
        if ($envKeys !== []) {
            $this->line('tey/kit ignores these. Delete them from '.basename($envFile).' (kit:brand:migrate never edits it):');
        }
        $outside = [];
        foreach ($envKeys as $key) {
            $name = (string) BrandConfig::envName($key);
            if (isset($lines[$name])) {
                $this->line('  '.$lines[$name]['line']);
            } else {
                $outside[] = $name;
            }
        }
        if ($outside !== []) {
            $this->line('Set in the environment, not .env (remove them where they are set: CI, deploy settings, scripts): '.implode(', ', $outside));
        }
        if ($deleteKitBrand !== null) {
            $this->line($deleteKitBrand.($fromKit === [] ? ' (every key in it is the kit\'s default).' : ' (written above: '.implode(', ', $fromKit).').'));
        }
        $this->warn($elsewhere);
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
        $out = "<?php\n\n// The app's brand, read by tey/kit as `kit.brand`. Written by `php artisan kit:brand:migrate` from the KIT_BRAND_* values and config/kit.php's brand at the time.\n"
            ."// A key left out takes the kit's default. tey/kit does not read KIT_BRAND_* env values (removed in 0.17).\n"
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
