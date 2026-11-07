<?php

// kit:preflight fails closed: a row-level security problem, a probe that throws, or any failed tey/standards check is a non-zero exit, and only a clean run exits 0.
// A synthetic stand-in for standards:preflight returns a chosen exit; no database, host or provider.

require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\Features\Core\Deploy\PreflightCommand;

function preflightCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

final class FakeStandardsPreflight extends Command
{
    public static int $exit = 0;

    /** @var array<string, mixed> */
    public static array $options = [];

    protected $signature = 'standards:preflight {--skip=} {--ci} {--strict}';

    public function handle(): int
    {
        self::$options = $this->options();

        return self::$exit;
    }
}

try {
    $base = sys_get_temp_dir().'/kit-preflight-exit-'.bin2hex(random_bytes(4));
    mkdir($base, 0777, true);
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    $app = new Application($base);
    $app->instance('config', new Repository(['kit' => []]));
    Facade::setFacadeApplication($app);
    $artisan = new Artisan($app, new Dispatcher($app), 'test');
    $artisan->setAutoExit(false);
    $artisan->add($standards = new FakeStandardsPreflight);
    $artisan->add($preflight = new PreflightCommand);
    $run = function (array $args = []) use ($preflight): array {
        $input = new ArrayInput($args);
        $input->setInteractive(false);
        $output = new BufferedOutput;
        $code = $preflight->run($input, $output);

        return [$code, $output->fetch()];
    };

    [$code] = $run();
    preflightCheck($code === 0, 'a clean run exits 0');
    preflightCheck((FakeStandardsPreflight::$options['strict'] ?? false) === false, 'standards:preflight runs without --strict by default, so a host-dependent advisory does not fail the deploy');
    $run(['--strict' => true]);
    preflightCheck((FakeStandardsPreflight::$options['strict'] ?? false) === true, 'kit:preflight --strict passes --strict on');

    FakeStandardsPreflight::$exit = 1;
    [$code] = $run();
    preflightCheck($code === 1, 'a failed standards check exits non-zero');
    FakeStandardsPreflight::$exit = 2;
    [$code] = $run();
    preflightCheck($code === 1, 'a standards usage error exits non-zero');
    FakeStandardsPreflight::$exit = 0;

    $app->instance('kit.rls-problems', fn (): array => ['the runtime role app is a superuser, which bypasses RLS']);
    [$code, $out] = $run();
    preflightCheck($code === 1 && str_contains($out, 'Row-level security: the runtime role app is a superuser'), 'an RLS problem is printed and exits non-zero, though every standards check passed');

    $app->instance('kit.rls-problems', fn (): array => throw new RuntimeException('boom'));
    [$code, $out] = $run();
    preflightCheck($code === 1 && str_contains($out, 'could not run'), 'an RLS probe that throws fails closed');

    $app->instance('kit.rls-problems', fn (): array => []);
    [$code] = $run();
    preflightCheck($code === 0, 'with no RLS problem the run is clean again');

    echo "Preflight exit checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
