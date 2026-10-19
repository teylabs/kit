<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Throwable;

/** Writes the app-owned host binding kit:add needs. Never overwrites a file; a second run changes nothing. */
final class InstallCommand extends Command
{
    private const PROVIDER = 'App\\Providers\\KitHostServiceProvider';

    /** Target => stub in resources/install; the executor provenance is written from the runtime. */
    private const FILES = [
        'app/Kit/KitHost.php' => 'KitHost.php.stub',
        'app/Kit/operations.php' => 'operations.php.stub',
        'config/kit-lifecycle.php' => 'kit-lifecycle.php.stub',
        'config/kit-executor.json' => null,
        'app/Providers/KitHostServiceProvider.php' => 'KitHostServiceProvider.php.stub',
    ];

    protected $signature = 'kit:install';

    protected $description = 'Write the app-owned LifecycleHost binding kit:add and kit:update need';

    public function handle(): int
    {
        try {
            $root = $this->laravel->basePath();
            $providers = $root.'/bootstrap/providers.php';
            if (is_link($providers) || ! is_file($providers)) {
                throw new RuntimeException('bootstrap/providers.php is required.');
            }
            $existing = array_values(array_filter(array_keys(self::FILES), fn (string $path): bool => file_exists($root.'/'.$path) || is_link($root.'/'.$path)));
            $registered = $this->registered($providers);
            if (count($existing) === count(self::FILES) && $registered) {
                $this->info('kit is already installed; nothing changed.');

                return self::SUCCESS;
            }
            if ($existing !== []) {
                throw new RuntimeException("Refusing to overwrite existing files; nothing was written:\n  ".implode("\n  ", $existing));
            }
            if ($this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A LifecycleHost is already bound by this app; nothing was written.');
            }
            $runtime = (new Lifecycle)->runtime();
            $contents = [];
            foreach (self::FILES as $path => $stub) {
                $contents[$path] = $stub === null
                    ? json_encode($runtime, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n"
                    : str_replace('{{ app_id }}', $this->appId(), file_get_contents(dirname(__DIR__, 2).'/resources/install/'.$stub));
            }
            $source = $registered ? null : $this->register($providers);
            foreach ($contents as $path => $content) {
                if (! is_dir(dirname($root.'/'.$path))) {
                    mkdir(dirname($root.'/'.$path), 0755, true);
                }
                // 'x' refuses a file that appeared since the check above.
                $handle = fopen($root.'/'.$path, 'x');
                if ($handle === false || fwrite($handle, $content) !== strlen($content) || ! fclose($handle)) {
                    throw new RuntimeException('Could not write '.$path.'.');
                }
                $this->line('wrote '.$path);
            }
            if ($source !== null) {
                file_put_contents($providers, $source);
                $this->line('bootstrap/providers.php: added '.self::PROVIDER.'::class');
            }
            $this->newLine();
            $this->line('Review before kit:add:');
            $this->line('  app/Kit/operations.php: the operation check. It passes once the host schema (migrations, users) exists; add the checks your app relies on.');
            $this->line('  config/kit-lifecycle.php: app_id is '.$this->appId().'. Keep it once features are adopted.');
            $this->line('  config/kit-executor.json: the Mod runtime ('.$runtime['installed_mod_version'].'). Update it after upgrading tey/mod.');
            $this->line('  composer.lock is a verification input; kit:add needs it to exist.');
            $this->newLine();
            $this->line('Then: php artisan kit:add <features>');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function appId(): string
    {
        return Str::slug((string) config('app.name')) ?: 'app';
    }

    private function registered(string $providers): bool
    {
        return preg_match('/^\s*\\\\?'.preg_quote(self::PROVIDER, '/').'::class\s*,?\s*$/m', file_get_contents($providers)) === 1;
    }

    /** The providers file with the class line appended to its returned list; the rest stays as the host wrote it. */
    private function register(string $providers): string
    {
        $source = file_get_contents($providers);
        $end = strrpos($source, '];');
        if ($end === false) {
            throw new RuntimeException('bootstrap/providers.php must return a [...] list; add '.self::PROVIDER.'::class yourself. Nothing was written.');
        }
        $before = rtrim(substr($source, 0, $end));
        $separator = str_ends_with($before, '[') || str_ends_with($before, ',') ? '' : ',';

        return $before.$separator."\n    ".self::PROVIDER."::class,\n".substr($source, $end);
    }
}
