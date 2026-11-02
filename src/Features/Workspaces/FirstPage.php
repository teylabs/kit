<?php

namespace Tey\Kit\Features\Workspaces;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Registry;

/** A workspace's first primary navigation page: where landing in a workspace goes when it is not the dashboard. */
final class FirstPage
{
    /**
     * The URL of the first primary navigation link, in navigation order, whose index route exists and, given `$may`, whose ability the viewer holds; null when there is none.
     *
     * @param  ?Closure(string): bool  $may  whether the viewer holds a link's ability; null skips the check
     */
    public static function url(int $workspace, Request $request, ?Closure $may = null): ?string
    {
        foreach (array_filter(app(Registry::class)->navigationLinks(), fn (array $link) => $link['slot'] === 'primary') as $link) {
            $route = config('kit.routes.'.$link['suffix'].'.name', '').'workspaces.'.$link['suffix'].'.index';
            if (Route::has($route) && ($may === null || $may($link['ability']))) {
                return url(WorkspaceUrl::to($workspace, $link['suffix'], $link['suffix'], $request));
            }
        }

        return null;
    }
}
