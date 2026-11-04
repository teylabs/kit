<?php

namespace Tey\Kit\Features\Core\Live;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Inertia\LiveProp;
use InvalidArgumentException;
use LogicException;

/**
 * Makes view-model props Inertia live props on one broadcast notice (docs/live-props.md).
 *
 * `LiveProps::on($props, ['tiles', 'activity'], new BuildChanged($key), when: $featureOn)` returns `$props` with those keys
 * wrapped in `Inertia::live(...)`. When `when` is false, or the host's inertia-laravel predates live props (3.7), the props
 * come back unchanged and the page's fallback poll carries them.
 *
 * The notice must be `ShouldBroadcastNow`: a queued broadcast would need an always-on worker, which a scale-to-zero queue
 * doesn't have, so a queued event fails here rather than silently never arriving.
 */
final class LiveProps
{
    /** Whether the host's inertia-laravel has live props. */
    public static function supported(): bool
    {
        return class_exists(LiveProp::class);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function on(array $props, array $keys, object $notice, bool $when = true, ?int $throttle = null): array
    {
        if (! $notice instanceof ShouldBroadcastNow) {
            throw new InvalidArgumentException('A live prop notice must implement ShouldBroadcastNow: '.$notice::class.' does not.');
        }
        if (! $when || ! self::supported()) {
            return $props;
        }
        foreach ($keys as $key) {
            if (! array_key_exists($key, $props)) {
                throw new LogicException("There is no [{$key}] prop to make live.");
            }
            $value = $props[$key];
            $props[$key] = new LiveProp($value instanceof \Closure ? $value : fn (): mixed => $value, $notice, null, $throttle);
        }

        return $props;
    }
}
