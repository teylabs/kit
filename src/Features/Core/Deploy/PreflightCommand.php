<?php

namespace Tey\Kit\Features\Core\Deploy;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * The deploy preflight under the kit's name: tey/standards' full checks (the mail transport's package, the cache, session and queue tables, the keys, the extensions) with a non-zero exit on a
 * misconfigured production, plus which form the Passport keys arrived in. `--env-file=` rehearses a deploy against the values you would set in production: it runs the same checks in a
 * child process that reads that file instead of `.env`, and prints no value from it.
 */
final class PreflightCommand extends Command
{
    protected $signature = 'kit:preflight {--skip=* : Check names to skip} {--ci : Skip the checks that need production secrets} {--env-file= : Rehearse against this environment file instead of .env (production values pulled to a local file; nothing in it is printed)}';

    protected $description = 'Run the full deployment preflight and exit non-zero on a misconfigured production (optional; migrate and optimize are the whole deploy)';

    public function handle(): int
    {
        if ($this->option('env-file') !== null) {
            return $this->rehearse((string) $this->option('env-file'));
        }
        if (! array_key_exists('standards:preflight', $this->getApplication()?->all() ?? [])) {
            $this->error('tey/standards is not installed, so there is no preflight to run.');

            return self::FAILURE;
        }
        // standards takes its skips as one comma-separated string.
        $exit = $this->call('standards:preflight', array_filter(['--skip' => implode(',', (array) $this->option('skip')), '--ci' => $this->option('ci')]));
        // How the Passport keys arrived (never the keys): real newlines, escaped newlines or base64, all of which the kit turns into the PEM Passport needs at boot.
        $forms = array_filter($this->laravel->bound('kit.passport-key-forms') ? ($this->laravel->make('kit.passport-key-forms'))() : [], fn (string $form): bool => $form !== 'missing');
        if ($forms !== []) {
            $this->newLine();
            $this->line('Passport keys: '.implode(', ', array_map(fn (string $env, string $form): string => $env.' as '.$form, array_keys($forms), $forms)));
        }

        return $exit;
    }

    /** Runs the checks in a child that reads the given file as its whole environment, so the local `.env` cannot fill in what the production values leave out. */
    private function rehearse(string $path): int
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('There is no readable environment file at that path.');

            return self::FAILURE;
        }
        $contents = (string) file_get_contents($path);
        $name = 'kit-rehearsal-'.bin2hex(random_bytes(4));
        $copy = $this->laravel->basePath('.env.'.$name);
        file_put_contents($copy, $contents);
        chmod($copy, 0600);
        // What must never reach the output: the values whose names say they are secrets (a key, a password, a token, a URL that carries credentials), and each line of a multi-line one.
        $secrets = [];
        foreach (Dotenv::parse($contents) as $name => $value) {
            if (is_string($value) && strlen($value) >= 8 && preg_match('/KEY|SECRET|PASS|TOKEN|PRIVATE|DSN|URL/i', (string) $name) === 1) {
                $secrets[] = $value;
                foreach (preg_split('/\R/', $value) ?: [] as $line) {
                    if (strlen(trim($line)) >= 8 && ! str_starts_with(trim($line), '-----')) {
                        $secrets[] = trim($line);
                    }
                }
            }
        }
        usort($secrets, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        try {
            $command = [PHP_BINARY, $this->laravel->basePath('artisan'), 'kit:preflight', '--env='.$name, '--no-interaction'];
            foreach ((array) $this->option('skip') as $skip) {
                $command[] = '--skip='.$skip;
            }
            if ($this->option('ci')) {
                $command[] = '--ci';
            }
            // The file is the whole environment: its values win over the shell's, and the local `.env` is not read at all.
            $process = new Process($command, $this->laravel->basePath(), array_map('strval', Dotenv::parse($contents)), timeout: 120);
            $process->run();
            // Nothing from the file is printed: a value that somehow reached the output is replaced.
            $output = $process->getOutput().$process->getErrorOutput();
            foreach ($secrets as $value) {
                $output = str_replace($value, '***', $output);
            }
            $this->output->write($output);

            return $process->getExitCode() ?? self::FAILURE;
        } finally {
            @unlink($copy);
        }
    }
}
