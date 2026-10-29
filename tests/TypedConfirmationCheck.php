<?php

// Typed confirmation on the server: the operation behind a destructive descriptor re-checks the submitted `confirmation` against the text (the client's disabled
// Confirm button is never the guard), and a descriptor's `confirm.typed` survives the round trip to the page. No database.
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\TypedConfirmation;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function typedCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The validation message on `confirmation`, or null when the check passed. */
function refused(callable $check): ?string
{
    try {
        $check();
    } catch (ValidationException $exception) {
        return $exception->errors()['confirmation'][0] ?? 'other field';
    }

    return null;
}

try {
    $base = sys_get_temp_dir().'/kit-typed-confirm-'.bin2hex(random_bytes(4));
    mkdir($base.'/bootstrap/cache', 0777, true);
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    $app = new Application($base);
    $app->instance('env', 'testing');
    $app->instance('config', new Illuminate\Config\Repository(['app' => ['locale' => 'en', 'env' => 'testing'], 'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]]));
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();

    // A destructive operation as an app writes it: re-check first, then act.
    $replaced = 0;
    $replace = function (Request $request) use (&$replaced): void {
        TypedConfirmation::require($request, 'Acme Ltd');
        $replaced++;
    };
    $post = fn (array $data) => Request::create('/sources/1/replace', 'POST', $data);

    typedCheck(refused(fn () => $replace($post([]))) === 'Type “Acme Ltd” to confirm.' && $replaced === 0, 'no confirmation is refused, and nothing happens (the client gate is never trusted)');
    typedCheck(refused(fn () => $replace($post(['confirmation' => 'Acme'])))  !== null && $replaced === 0, 'a mismatch is refused');
    typedCheck(refused(fn () => $replace($post(['confirmation' => 'acme ltd']))) !== null && $replaced === 0, 'case matters by default');
    typedCheck(refused(fn () => $replace($post(['confirmation' => ['Acme Ltd']]))) !== null && $replaced === 0, 'a non-string confirmation is refused');
    typedCheck(refused(fn () => $replace($post(['confirmation' => ' Acme Ltd ']))) === null && $replaced === 1, 'the exact text (surrounding whitespace ignored) is accepted');
    typedCheck(refused(fn () => TypedConfirmation::require(['confirmation' => 'ACME LTD'], 'Acme Ltd', caseSensitive: false)) === null, 'case-insensitive when asked, from an array too');
    typedCheck(refused(fn () => TypedConfirmation::require(['confirmation' => ''], '')) !== null, 'an empty expected text never passes');

    // The descriptor round trip.
    $action = new Action('Replace from source', '/sources/1/replace', 'post', confirm: TypedConfirmation::confirm('Replace from source?', 'This overwrites every record with the source.', 'Replace', 'Acme Ltd'), key: 'replace', destructive: true);
    $array = $action->toArray();
    typedCheck($array['confirm'] === ['title' => 'Replace from source?', 'description' => 'This overwrites every record with the source.', 'label' => 'Replace', 'typed' => 'Acme Ltd'], 'the descriptor carries confirm.typed to the page');
    $back = Action::from($array);
    typedCheck(($back->confirm['typed'] ?? null) === 'Acme Ltd' && json_decode(json_encode($action), true)['confirm']['typed'] === 'Acme Ltd', 'and it survives JSON and Action::from()');
    $plain = (new Action('Archive', '/x', confirm: ['title' => 'Archive?', 'description' => 'It can be restored.', 'label' => 'Archive']))->toArray();
    typedCheck(! isset($plain['confirm']['typed']), 'an ordinary confirm is unchanged');

    echo "Typed confirmation checks: the server helper and the descriptor, no database. The dialog's gating is tests/js/typed-confirmation.test.mjs.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
