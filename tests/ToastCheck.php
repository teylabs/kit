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

toastCheck(Toast::success('Saved')->toArray() === ['type' => 'success', 'message' => 'Saved', 'style' => 'fly-out', 'duration' => Toast::DURATION] && $flashed['toast']['message'] === 'Saved', 'a save toast flashes `toast`, flying out, with the short duration');
toastCheck(Toast::info('Import finished', '42 rows')->toArray() === ['type' => 'info', 'message' => 'Import finished', 'description' => '42 rows', 'style' => 'plain', 'duration' => Toast::DURATION], 'any other type is plain by default');
$controller = new class
{
    use Toastable;

    public function save(): array
    {
        return $this->toast('Changes saved')->from('save')->toArray();
    }
};
toastCheck($controller->save() === ['type' => 'success', 'message' => 'Changes saved', 'from' => 'save', 'style' => 'fly-out', 'duration' => Toast::DURATION] && $flashed['toast']['from'] === 'save', 'the Toastable concern names the trigger, and the flash follows the chain');
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
