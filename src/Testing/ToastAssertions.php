<?php

namespace Tey\Kit\Testing;

use BackedEnum;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Core\InertiaUIToasts;

/**
 * `assertToast()` and `assertNoToasts()` on TestResponse, named and shaped like inertiaui/notifications' own, so an app's tests read the same before and after it
 * moves to that package (docs/toasts.md#testing). They find the toasts a response delivers: flashed for the next page after a redirect (or an Inertia location
 * or redirect header), in an Inertia page's flash data (JSON or the first HTML view), or in the package's `X-InertiaUI-Toasts` header. Both the kit's `toast`
 * key and the package's list count, each toast in the package's shape (`title`, `type`, `actions`, `data.kit.from` / `data.kit.style`), so a callback reads one
 * shape under either driver. Registered once the app has booted, so with the package installed these (which read both) are the ones that run.
 */
final class ToastAssertions
{
    private const HEADER = 'X-InertiaUI-Toasts';

    public static function register(): void
    {
        TestResponse::macro('assertToast', function (Closure|string $expected, BackedEnum|string|null $type = null): TestResponse {
            /** @var TestResponse<Response> $this */
            $type = $type instanceof BackedEnum ? (string) $type->value : $type;
            $toasts = ToastAssertions::from($this);
            $matches = array_filter($toasts, fn (array $toast): bool => ($type === null || ($toast['type'] ?? 'default') === $type)
                && (is_string($expected) ? ($toast['title'] ?? null) === $expected : (bool) $expected($toast)));
            $wanted = (is_string($expected) ? "a toast titled [{$expected}]" : 'a matching toast').($type === null ? '' : " of type [{$type}]");
            PHPUnit::assertNotEmpty($matches, "Expected the response to deliver {$wanted}. Delivered: ".ToastAssertions::titles($toasts).'.');

            return $this;
        });

        TestResponse::macro('assertNoToasts', function (): TestResponse {
            /** @var TestResponse<Response> $this */
            $toasts = ToastAssertions::from($this);
            PHPUnit::assertSame([], $toasts, 'Expected the response to deliver no toasts. Delivered: '.ToastAssertions::titles($toasts).'.');

            return $this;
        });
    }

    /**
     * The toasts the response delivers, in the package's shape.
     *
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    public static function from(TestResponse $response): array
    {
        $key = (string) config('inertia-notifications.flash_key', 'inertiaui');
        $base = $response->baseResponse;
        $flashes = [];
        // Flashed for the next page arrives with the page a redirect leads to; any other response leaves it in the session. A 201 is no redirect the browser follows.
        $redirects = $base->isRedirect() && $base->getStatusCode() !== Response::HTTP_CREATED;
        if ($redirects || $base->headers->has('X-Inertia-Location') || $base->headers->has('X-Inertia-Redirect')) {
            $flashes[] = Inertia::getFlashed($response->baseRequest);
        }
        if ($base->headers->has('X-Inertia')) {
            $flashes[] = (array) ($response->json('flash') ?? []);
        } elseif (($original = $base->original ?? null) instanceof View) {
            $flashes[] = (array) ($original->getData()['page']['flash'] ?? []);
        }
        $header = json_decode((string) $base->headers->get(self::HEADER, '[]'), true);
        $toasts = is_array($header) ? $header : [];
        foreach ($flashes as $flash) {
            $toasts = [...$toasts, ...(array) ($flash[$key]['toasts'] ?? [])];
            if (is_array($flash['toast'] ?? null) && isset($flash['toast']['id'], $flash['toast']['message'])) {
                $toasts[] = InertiaUIToasts::payload($flash['toast']);
            }
        }
        // One toast per uuid: a flash read from both the redirect and the page it leads to is one delivery.
        $unique = [];
        foreach ($toasts as $toast) {
            if (is_array($toast)) {
                $unique[$toast['uuid'] ?? count($unique)] = $toast;
            }
        }

        return array_values($unique);
    }

    /** @param list<array<string, mixed>> $toasts */
    public static function titles(array $toasts): string
    {
        return $toasts === [] ? 'none' : implode(', ', array_map(fn (array $toast): string => '['.Arr::get($toast, 'title').'] ('.Arr::get($toast, 'type', 'default').')', $toasts));
    }
}
