<?php

// LiveProps::on: view-model props made Inertia live props on one ShouldBroadcastNow notice, only while `when` holds; a queued
// notice, a missing prop or an empty notice fails. No app boot, no broadcaster: the listeners are read off the LiveProp itself.
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Inertia\LiveProp;
use Tey\Kit\Features\Core\Live\LiveProps;

require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';

function livePropsCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

final class LivePropsCheckNotice implements ShouldBroadcastNow
{
    public function __construct(public string $key) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('authoring.'.$this->key)];
    }

    public function broadcastAs(): string
    {
        return 'invalidated';
    }
}

final class LivePropsCheckQueued implements ShouldBroadcast
{
    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('authoring.k')];
    }
}

function livePropsThrows(callable $run, string $class, string $needle): bool
{
    try {
        $run();
    } catch (Throwable $e) {
        return $e instanceof $class && str_contains($e->getMessage(), $needle);
    }

    return false;
}

try {
    livePropsCheck(LiveProps::supported(), 'the installed inertia-laravel has live props (3.7 or later)');

    $props = ['tiles' => [1, 2], 'activity' => fn (): array => ['a'], 'title' => 'Deck'];
    $live = LiveProps::on($props, ['tiles', 'activity'], new LivePropsCheckNotice('talk-a'));
    $listener = [['channel' => ['name' => 'authoring.talk-a', 'type' => 'private'], 'events' => ['invalidated']]];
    livePropsCheck($live['tiles'] instanceof LiveProp && $live['activity'] instanceof LiveProp, 'the named props become live props');
    livePropsCheck($live['title'] === 'Deck', 'the other props are untouched');
    livePropsCheck($live['tiles']->liveListeners() === $listener && $live['activity']->liveListeners() === $listener, "each listens on the notice's private channel for its broadcast name");
    // Inertia resolves the value through the container at response time; read the wrapped closure directly here.
    $value = fn (LiveProp $prop): mixed => (fn () => $this->value)->call($prop);
    livePropsCheck($value($live['tiles']) instanceof Closure && $value($live['tiles'])() === [1, 2], 'a plain value is wrapped in a closure that returns it');
    livePropsCheck($value($live['activity']) === $props['activity'], 'a closure is passed through as is, so it still runs lazily');

    $throttled = LiveProps::on($props, ['tiles'], new LivePropsCheckNotice('talk-a'), throttle: 3000);
    livePropsCheck($throttled['tiles']->liveThrottle() === 3000, 'a throttle is passed through');

    livePropsCheck(LiveProps::on($props, ['tiles'], new LivePropsCheckNotice('talk-a'), when: false) === $props, 'with `when` false the props come back unchanged');

    livePropsCheck(livePropsThrows(fn () => LiveProps::on($props, ['tiles'], new LivePropsCheckQueued), InvalidArgumentException::class, 'ShouldBroadcastNow'), 'a queued (ShouldBroadcast) notice fails');
    livePropsCheck(livePropsThrows(fn () => LiveProps::on($props, ['tiles'], new LivePropsCheckQueued, when: false), InvalidArgumentException::class, 'ShouldBroadcastNow'), 'a queued notice fails even with `when` false, so it shows up before the flag is turned on');
    livePropsCheck(livePropsThrows(fn () => LiveProps::on($props, ['missing'], new LivePropsCheckNotice('talk-a')), LogicException::class, '[missing]'), 'a prop that is not there fails');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
echo "LiveProps checks against the installed inertia-laravel; no broadcaster or app needed.\n";
