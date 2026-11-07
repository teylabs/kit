<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\Themes\Themes;

/** Lists the theme presets, or sets the app's default (`KIT_THEME` in the app's .env). */
final class ThemeCommand extends Command
{
    protected $signature = 'kit:theme {name? : The preset to make the default; leave out to list them}';

    protected $description = 'List the theme presets, or set the app default (KIT_THEME)';

    public function handle(): int
    {
        try {
            $current = config('kit.themes.default');
            $name = $this->argument('name');
            if (! is_string($name) || $name === '') {
                foreach (Themes::all() as $preset) {
                    $this->line(($preset->name === $current ? '* ' : '  ').$preset->name.'  '.$preset->label.($preset->isDraft() ? '  (draft)' : ''));
                }
                if (! is_string($current) || $current === '') {
                    $this->line('No default: the brand values apply. Set one with php artisan kit:theme <name>.');
                }

                return self::SUCCESS;
            }
            if (Themes::find($name) === null) {
                throw new RuntimeException("Unknown theme preset: {$name}. One of: ".implode(', ', array_keys(Themes::all())).'.');
            }
            $this->writeEnv(app()->environmentFilePath(), $name);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->line("KIT_THEME={$name} written to .env. Clear the config cache if it is cached.");

        return self::SUCCESS;
    }

    private function writeEnv(string $file, string $name): void
    {
        if (! is_file($file)) {
            throw new RuntimeException("No .env at {$file}: set KIT_THEME={$name} in the environment instead.");
        }
        $contents = (string) file_get_contents($file);
        $line = 'KIT_THEME='.$name;
        $contents = preg_match('/^KIT_THEME=.*$/m', $contents) === 1
            ? (string) preg_replace('/^KIT_THEME=.*$/m', $line, $contents)
            : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
        file_put_contents($file, $contents);
    }
}
