<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Tey\Kit\Features\Core\EnvironmentIndicator;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\SessionUi\PasskeyOffer;
use Tey\Kit\Features\SessionUi\PasswordPolicy;
use Tey\Kit\Features\Shell\ShellSource;

class HandleInertiaRequests extends Middleware
{
    /** The registry's request hooks run first (the session has started by now: the brand's local-only `?brand=` switch), then Inertia. */
    public function handle(Request $request, \Closure $next)
    {
        app(Registry::class)->prepareRequest($request);

        return parent::handle($request, $next);
    }

    public function share(Request $request): array
    {
        // One share, `shell`: the product and viewer, the workspace switcher, the primary navigation (already filtered by what the viewer may open; there is no `can` map anywhere, pages get action descriptors). Everything about the page itself
        // (section navigation, heading, document title, breadcrumbs) is the page ViewModel's `layout`, never a second producer. Gretel shares its own `breadcrumbs`, and no
        // page reads it any more: it is cleared here, so there is one source for the trail.
        // A server-driven modal opened by URL has shared its own data before the base page is dispatched through here: keep it across the flush.
        $modal = Inertia::getShared('_inertiaui_modal');
        // The `shell` prop is the shell feature's share (ShellProvider, built by the bound ShellSource, which session-ui makes its PageShell): kept across the flush, never shared twice.
        $shell = Inertia::getShared('shell') ?? fn (): array => app(ShellSource::class)->forRequest(request())->toArray();
        Inertia::flushShared();
        if ($modal !== null) {
            Inertia::share('_inertiaui_modal', $modal);
        }

        // The flush above also drops core's `kitUi` and `kitToasts` (KitServiceProvider), so they are shared again here.
        return [...parent::share($request), 'kitUi' => fn (): array => (array) trans('kit::ui'), 'kitToasts' => fn (): array => Toast::shared(), 'environment' => fn (): ?array => EnvironmentIndicator::current(), 'kitPassword' => fn (): array => PasswordPolicy::client(), 'passkeyOffer' => fn (): ?array => PasskeyOffer::shared($request->user('web')), 'shell' => $shell];
    }
}
