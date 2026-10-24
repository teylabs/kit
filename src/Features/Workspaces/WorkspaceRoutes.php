<?php

namespace Tey\Kit\Features\Workspaces;

use Closure;
use Glhd\Gretel\Registry;
use Glhd\Gretel\Resolvers\UrlResolver;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route as Router;

/** Defines a feature's workspace pages twice: the full form under `/@{workspace}` and the short form without it. */
final class WorkspaceRoutes
{
    /**
     * @param  callable(bool): void  $routes  declares the routes; the argument is true for the full form (name pages with `page` then)
     * @param  list<string>  $middleware  added after the sign-in checks
     */
    public static function define(callable $routes, array $middleware = []): void
    {
        // The current application's router, not the facade's cached one: a second application booted in the same process declares on its own.
        $router = app('router');
        $router->middleware(['auth:web', 'concierge.active', Http\Middleware\ResolveWorkspace::class, ...$middleware])->group(function () use ($routes, $router): void {
            $router->prefix('@{workspace}')->where(['workspace' => Slugs::PATTERN])->group(fn () => $routes(true));
            $routes(false);
        });
    }

    /**
     * Names a workspace page and gives it a breadcrumb: a name and a trail for the full form, and `short.` ones for the short
     * form, which trails through the same parents. Each crumb links through the URL helper, in the form the session uses.
     *
     * @param  string|Closure  $title  the crumb's title; a closure takes the route's parameters, in order
     * @param  ?array{0: string, 1: string}  $parent  the parent page's feature and route name
     */
    public static function page(Route $route, bool $full, string $feature, string $name, string|Closure $title, ?array $parent = null): Route
    {
        $route->name($full ? $name : 'short.'.$name);
        $route->breadcrumb($title, $parent === null ? null : self::name(...$parent));
        $fullName = self::name($feature, $name);
        $names = $full ? $route->parameterNames() : ['workspace', ...$route->parameterNames()];
        // gretel's macros keep the breadcrumb registry of the first application booted in a process: in a later one (a test suite) there is nothing to give a resolver to.
        if (($crumb = app(Registry::class)->get($route)) !== null) {
            $crumb->url = new UrlResolver(static function (array $parameters) use ($fullName, $names): string {
                $values = Arr::only($parameters, $names);
                // A personal page has no workspace of its own: its links to workspace pages use the current one, or the chooser.
                $workspace = (int) ($parameters['workspace'] ?? WorkspaceUrl::currentMemberId() ?? 0);
                if ($workspace === 0) {
                    return '/me/workspaces';
                }
                $values['workspace'] = WorkspaceUrl::slug($workspace);

                return WorkspaceUrl::localise(route($fullName, $values));
            });
        }

        return $route;
    }

    /** A personal page (under `/me`): a breadcrumb whose link is the page's own path, as the workspace pages' are. */
    public static function personalPage(Route $route, string|Closure $title, ?array $parent = null): Route
    {
        $route->breadcrumb($title, $parent === null ? null : self::name(...$parent));
        $name = $route->getName();
        $names = $route->parameterNames();
        app(Registry::class)->get($route)->url = new UrlResolver(static function (array $parameters) use ($name, $names): string {
            $path = parse_url(route($name, Arr::only($parameters, $names)));

            return ($path['path'] ?? '/').(isset($path['query']) ? '?'.$path['query'] : '');
        });

        return $route;
    }

    /** A route's final name: the feature's configured name prefix, then its own name. */
    public static function name(string $feature, string $name): string
    {
        return config('kit.routes.'.$feature.'.name', '').$name;
    }
}
