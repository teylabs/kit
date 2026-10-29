<?php

namespace Tey\Kit\Features\Core;

use Closure;
use Glhd\Gretel\Registry;
use Glhd\Gretel\Resolvers\UrlResolver;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;

/** A page outside any workspace (an account page, a welcome step): a breadcrumb whose link is the page's own path. Needs no workspace, so session-ui uses it on a core and shell host. */
final class PersonalPages
{
    /** @param  ?array{0: string, 1: string}  $parent  the parent page's feature and route name, before the feature's name prefix */
    public static function page(Route $route, string|Closure $title, ?array $parent = null): Route
    {
        $route->breadcrumb($title, $parent === null ? null : config('kit.routes.'.$parent[0].'.name', '').$parent[1]);
        $name = $route->getName();
        $names = $route->parameterNames();
        app(Registry::class)->get($route)->url = new UrlResolver(static function (array $parameters) use ($name, $names): string {
            $path = parse_url(route($name, Arr::only($parameters, $names)));

            return ($path['path'] ?? '/').(isset($path['query']) ? '?'.$path['query'] : '');
        });

        return $route;
    }
}
