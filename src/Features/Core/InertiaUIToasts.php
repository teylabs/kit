<?php

namespace Tey\Kit\Features\Core;

use Inertia\Inertia;
use InertiaUI\Notifications\FlashedToasts;
use LogicException;

/**
 * The shim behind `kit.toasts.driver` `inertiaui`: a kit toast (Toast::toArray()) flashed into inertiaui/notifications' list, so its Toaster renders it and
 * its `toast()`, header and test helpers see it. The kit's calls and payload rules stay; only the delivery changes.
 * - Shape: `{uuid, id, type, title, description?, actions?, duration, data: {kit: {from?, style}}}`. `uuid` and `id` are the kit's ULID, so a re-flash
 *   (`->from()`, `->style()`) replaces its own entry, and the kit's shim Toaster finds the rendered toast (`data-toast-id`) to pulse or fly it from its trigger.
 * - `anchored` shows no corner toast, so it stays on the kit's own `toast` key, which only the shim Toaster reads (a bubble on the trigger).
 * - The kit's duration is sent as is: a number, or null to stay until dismissed.
 */
final class InertiaUIToasts
{
    /** @param array<string, mixed> $toast Toast::toArray() */
    public static function flash(array $toast): void
    {
        if (! class_exists(FlashedToasts::class)) {
            throw new LogicException('kit.toasts.driver is inertiaui, but inertiaui/notifications is not installed (composer require inertiaui/notifications).');
        }
        $id = $toast['id'];
        FlashedToasts::put(array_values(array_filter(FlashedToasts::get(), fn (array $flashed): bool => ($flashed['uuid'] ?? null) !== $id)));
        if (($toast['style'] ?? null) === 'anchored') {
            Inertia::flash('toast', $toast);

            return;
        }
        if ((Inertia::getFlashed()['toast']['id'] ?? null) === $id) {
            Inertia::forgetFlashed('toast');
        }
        FlashedToasts::push(self::payload($toast));
    }

    /**
     * The package's toast for a kit toast.
     *
     * @param  array<string, mixed>  $toast  Toast::toArray()
     * @return array<string, mixed>
     */
    public static function payload(array $toast): array
    {
        $payload = ['uuid' => $toast['id'], 'id' => $toast['id'], 'type' => $toast['type'], 'title' => $toast['message']];
        if (isset($toast['description'])) {
            $payload['description'] = $toast['description'];
        }
        if (isset($toast['action'])) {
            $action = $toast['action'];
            $payload['actions'] = [array_filter(['label' => $action['label'], 'href' => $action['href'], 'method' => $action['method'] === 'get' ? null : $action['method']])];
        }
        $payload['duration'] = $toast['duration'];
        $payload['data'] = ['kit' => array_filter(['from' => $toast['from'] ?? null, 'style' => $toast['style']])];

        return $payload;
    }
}
