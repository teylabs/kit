<?php

use Illuminate\Support\Facades\Facade;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Concerns\Toastable;
use Tey\Kit\Features\Core\Toast;

require dirname(__DIR__).'/vendor/autoload.php';

/** The toast helper: the payload a controller flashes, the rules it refuses, the trigger and style, and the shared position. Synthetic config, no HTTP. */
function toastCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function refuses(callable $call): bool
{
    try {
        $call();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
}

/** A payload without its id, which is unique per toast. */
function withoutId(array $toast): array
{
    unset($toast['id']);

    return $toast;
}

$app = new Illuminate\Foundation\Application(dirname(__DIR__));
Illuminate\Container\Container::setInstance($app);
$app->instance('config', new Illuminate\Config\Repository(['kit' => []]));
Facade::setFacadeApplication($app);
$flashed = [];
Inertia::swap(new class($flashed)
{
    public function __construct(private array &$flashed) {}

    public function flash(string $key, mixed $value): self
    {
        $this->flashed[$key] = $value;

        return $this;
    }
});

toastCheck(withoutId(Toast::success('Saved')->toArray()) === ['type' => 'success', 'message' => 'Saved', 'style' => 'pulse', 'duration' => Toast::DURATION] && $flashed['toast']['message'] === 'Saved', 'a save toast flashes `toast`, with a pulse, with the short duration');
toastCheck(withoutId(Toast::info('Import finished', '42 rows')->toArray()) === ['type' => 'info', 'message' => 'Import finished', 'description' => '42 rows', 'style' => 'plain', 'duration' => Toast::DURATION], 'any other type is plain by default');
$controller = new class
{
    use Toastable;

    public function save(): array
    {
        return $this->toast('Changes saved')->from('save')->toArray();
    }
};
toastCheck(withoutId($controller->save()) === ['type' => 'success', 'message' => 'Changes saved', 'from' => 'save', 'style' => 'pulse', 'duration' => Toast::DURATION] && $flashed['toast']['from'] === 'save', 'the Toastable concern names the trigger, and the flash follows the chain');
Toast::success('Role changed')->from('change-role')->style('anchored');
toastCheck($flashed['toast']['style'] === 'anchored' && $flashed['toast']['from'] === 'change-role', 'a style re-flashes the same toast');
toastCheck(Toast::warning('Heads up')->style('pulse')->toArray()['style'] === 'pulse' && Toast::error('Could not reach the bank')->toArray()['type'] === 'error', 'warning and error are types, and pulse is a style');
$undo = Toast::success('Moved', null, ['label' => 'Undo', 'href' => '/cases/1/restore', 'method' => 'POST'])->toArray();
toastCheck($undo['action'] === ['label' => 'Undo', 'href' => '/cases/1/restore', 'method' => 'post'] && $undo['duration'] === Toast::ACTION_DURATION && Toast::ACTION_DURATION > Toast::DURATION, 'an action is one item and stays up longer');
toastCheck(! array_key_exists('description', Toast::success('Saved', '  ')->toArray()), 'a blank description is left out');

foreach (['Delete', 'remove row', 'Discard draft', 'Revoke key', 'Cancel plan'] as $label) {
    toastCheck(refuses(fn () => Toast::success('Saved', null, ['label' => $label, 'href' => '/x'])), "a destructive action ({$label}) is refused");
}
toastCheck(refuses(fn () => Toast::success('Saved', null, ['label' => 'Undo', 'href' => '/x', 'method' => 'delete'])), 'a DELETE action is refused');
toastCheck(refuses(fn () => Toast::success('Saved', null, ['label' => 'Undo'])), 'an action needs an href');
toastCheck(refuses(fn () => Toast::success('Saved', null, ['label' => 'Undo', 'href' => '/x', 'actions' => []])), 'an action is one item, with no extra keys');
toastCheck(refuses(fn () => Toast::success(' ')), 'a toast needs a message');
toastCheck(refuses(fn () => Toast::success('Saved')->style('confetti')), 'an unknown style is refused');
toastCheck(refuses(fn () => Toast::success('Saved')->from(' ')), 'a blank trigger is refused');

toastCheck(Toast::shared() === ['position' => 'auto'], 'the position defaults to auto');
foreach (['top-center', 'top-right', 'bottom-right', 'bottom-center'] as $position) {
    config(['kit.toasts.position' => $position]);
    toastCheck(Toast::shared() === ['position' => $position], "{$position} is shared as set");
}
config(['kit.toasts.position' => 'top-left']);
toastCheck(Toast::shared() === ['position' => 'auto'], 'an unknown position falls back to auto');
$shipped = require dirname(__DIR__).'/config/kit.php';
toastCheck($shipped['toasts']['position'] === 'auto', 'the shipped config is auto');

// Durations per type, from kit.toasts.durations at flash time; null stays until dismissed.
config(['kit.toasts' => ['position' => 'auto']]);
toastCheck(Toast::success('Saved')->toArray()['duration'] === 4000 && Toast::info('Note')->toArray()['duration'] === 4000, 'without durations config: success and info stay 4000 ms');
toastCheck(array_key_exists('duration', Toast::warning('Careful')->toArray()) && Toast::warning('Careful')->toArray()['duration'] === null && Toast::error('Failed')->toArray()['duration'] === null, 'without durations config: warning and error are persistent (null)');
$action = ['label' => 'Open', 'href' => '/imports/1'];
toastCheck(Toast::success('Imported', null, $action)->toArray()['duration'] === Toast::ACTION_DURATION && Toast::error('Failed', null, $action)->toArray()['duration'] === null, 'an action keeps a timed toast up 8000 ms, and leaves a persistent one persistent');
config(['kit.toasts.durations' => ['success' => 2500, 'info' => null, 'warning' => 6000, 'error' => 10000]]);
toastCheck(Toast::success('Saved')->toArray()['duration'] === 2500 && Toast::info('Note')->toArray()['duration'] === null && Toast::warning('Careful')->toArray()['duration'] === 6000 && Toast::error('Failed')->toArray()['duration'] === 10000, 'each type resolves from config at flash time');
toastCheck(Toast::success('Imported', null, $action)->toArray()['duration'] === 8000 && Toast::error('Failed', null, $action)->toArray()['duration'] === 10000 && Toast::info('Note', null, $action)->toArray()['duration'] === null, 'with an action: at least 8000 ms when timed, longer config kept, null kept');
config(['kit.toasts.durations' => ['success' => 'soon', 'warning' => -1]]);
toastCheck(Toast::success('Saved')->toArray()['duration'] === 4000 && Toast::warning('Careful')->toArray()['duration'] === null && Toast::info('Note')->toArray()['duration'] === 4000, 'an unusable or missing value is the type\'s default');
toastCheck($shipped['toasts']['durations'] === ['success' => 4000, 'info' => 4000, 'warning' => null, 'error' => null] && $shipped['toasts']['durations'] === Toast::DURATIONS, 'the shipped durations match the defaults');

// Each toast carries a unique id, kept across its own re-flashes, so the Toaster shows it once and two identical saves twice.
$first = Toast::success('Saved');
$second = Toast::success('Saved');
toastCheck(is_string($first->toArray()['id']) && strlen($first->toArray()['id']) === 26 && $first->toArray()['id'] !== $second->toArray()['id'] && array_key_first($first->toArray()) === 'id', 'each flashed toast gets a distinct ULID id');
$styled = Toast::success('Saved');
$id = $styled->toArray()['id'];
toastCheck($styled->from('save')->style('anchored')->toArray()['id'] === $id && $flashed['toast']['id'] === $id, 'a toast keeps its id across its own re-flashes (from, style)');
