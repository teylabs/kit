<?php

namespace Tey\Kit\Testing;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Routing\PublicRoute;
use Tey\Kit\Features\Workspaces\Http\Middleware\ResolveWorkspace;

/**
 * Test support only. The route table's rules, read from the routes themselves instead of a stored copy of the table: a new route that follows them needs no
 * test edit, and one that breaks them is named. Each method returns the offending routes ("METHODS uri", with a reason), empty when all is well:
 *
 *     expect(RouteInvariants::all(['App\\']))->toBe([]);
 *
 * - shortTwins(): every workspace route is reachable both as `@{workspace}/x` and as the short `x`, with the same methods and middleware (the workspace
 *   root `@{workspace}` alone has no short form: `/` is the home page);
 * - workspaceStack(): a workspace route signs in (`auth:web`), refuses an inactive account (`concierge.active`) and resolves the workspace, in that
 *   order, with any `workspace.ability:*` after the workspace is resolved;
 * - guardedWrites($namespaces): a workspace write whose controller is in one of `$namespaces` carries `workspace.ability:*`, or its controller declares
 *   #[AuthorizedBy] an operation class that exists;
 * - declaredPublic($namespaces): a route with no auth middleware whose controller is in one of `$namespaces` declares #[PublicRoute] with a reason and
 *   has a `signed` or `throttle` middleware, or declares itself an open read (`open: true`, GET and HEAD only).
 *
 * Rules 3 and 4 take the namespaces you own (or exact controller classes); another package's controllers are its own to check. Rules 1 and 2 hold for every route.
 */
final class RouteInvariants
{
    /** @param list<string> $namespaces @return array<string, list<string>> each rule's offending routes, only the rules that have any */
    public static function all(array $namespaces): array
    {
        return array_filter([
            'shortTwins' => self::shortTwins(),
            'workspaceStack' => self::workspaceStack(),
            'guardedWrites' => self::guardedWrites($namespaces),
            'declaredPublic' => self::declaredPublic($namespaces),
        ]);
    }

    /** @return list<string> */
    public static function shortTwins(): array
    {
        $table = self::table();
        $wrong = [];
        foreach ($table as $key => $route) {
            [$methods, $uri] = explode(' ', $key, 2);
            if (! self::inWorkspace($route) || $uri === '@{workspace}') {
                continue;
            }
            $twin = str_starts_with($uri, '@{workspace}') ? $methods.' '.ltrim(substr($uri, strlen('@{workspace}')), '/') : $methods.' @{workspace}'.($uri === '/' ? '' : '/'.$uri);
            if (! isset($table[$twin])) {
                $wrong[] = $key.' (no '.$twin.')';
            } elseif ($table[$twin]->gatherMiddleware() !== $route->gatherMiddleware()) {
                $wrong[] = $key.' (its twin\'s middleware differs)';
            }
        }

        return $wrong;
    }

    /** @return list<string> */
    public static function workspaceStack(): array
    {
        $wrong = [];
        foreach (self::table() as $key => $route) {
            if (! self::inWorkspace($route)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $at = fn (string $name): int|false => array_search($name, $middleware, true);
            [$auth, $active, $resolve] = [$at('auth:web'), $at('concierge.active'), $at(ResolveWorkspace::class)];
            $abilities = array_keys(array_filter($middleware, fn ($m): bool => is_string($m) && str_starts_with($m, 'workspace.ability:')));
            if ($auth === false || $active === false || ! ($auth < $active && $active < $resolve) || ($abilities !== [] && min($abilities) < $resolve)) {
                $wrong[] = $key.': '.implode(',', array_map(fn ($m): string => is_string($m) ? $m : get_debug_type($m), $middleware));
            }
        }

        return $wrong;
    }

    /** @param list<string> $namespaces @return list<string> */
    public static function guardedWrites(array $namespaces): array
    {
        $wrong = [];
        foreach (self::table() as $key => $route) {
            $controller = self::controller($route);
            if ($controller === null || ! self::inWorkspace($route) || ! self::owned($controller, $namespaces) || array_diff($route->methods(), ['GET', 'HEAD']) === []) {
                continue;
            }
            $ability = array_filter($route->gatherMiddleware(), fn ($m): bool => is_string($m) && str_starts_with($m, 'workspace.ability:'));
            $declared = self::attributes($controller, AuthorizedBy::class);
            foreach ($declared as $by) {
                if (! class_exists($by->operation)) {
                    $wrong[] = $key.' → '.$controller.' names '.$by->operation.', which does not exist';
                }
            }
            if ($ability === [] && $declared === []) {
                $wrong[] = $key.' → '.$controller.' (no workspace.ability middleware and no #[AuthorizedBy])';
            }
        }

        return array_values(array_unique($wrong));
    }

    /** @param list<string> $namespaces @return list<string> */
    public static function declaredPublic(array $namespaces): array
    {
        $wrong = [];
        foreach (self::table() as $key => $route) {
            $controller = self::controller($route);
            if ($controller === null || ! self::owned($controller, $namespaces)) {
                continue;
            }
            $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
            if (array_filter($middleware, fn (string $m): bool => $m === 'auth' || str_starts_with($m, 'auth:')) !== []) {
                continue;
            }
            $declared = self::attributes($controller, PublicRoute::class);
            $limited = array_filter($middleware, fn (string $m): bool => in_array($m, ['signed', 'throttle'], true) || str_starts_with($m, 'signed:') || str_starts_with($m, 'throttle:'));
            if ($declared === [] || trim($declared[0]->reason) === '') {
                $wrong[] = $key.' → '.$controller.' (no #[PublicRoute] with a reason)';
            } elseif ($declared[0]->open && array_diff($route->methods(), ['GET', 'HEAD']) !== []) {
                $wrong[] = $key.' → '.$controller.' (declared open, but it writes)';
            } elseif ($limited === [] && ! $declared[0]->open) {
                $wrong[] = $key.' → '.$controller.' (public, but neither signed nor throttled)';
            }
        }

        return $wrong;
    }

    /** @return array<string, RoutingRoute> by "METHODS uri" */
    private static function table(): array
    {
        $table = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $table[implode('|', $route->methods()).' '.$route->uri()] = $route;
        }

        return $table;
    }

    private static function inWorkspace(RoutingRoute $route): bool
    {
        return in_array(ResolveWorkspace::class, $route->gatherMiddleware(), true);
    }

    private static function controller(RoutingRoute $route): ?string
    {
        $action = $route->getActionName();
        $class = ltrim(str_contains($action, '@') ? strstr($action, '@', true) : $action, '\\');

        return class_exists($class) ? $class : null;
    }

    /** @param list<string> $namespaces */
    private static function owned(string $class, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if ($class === $namespace || str_starts_with($class, rtrim($namespace, '\\').'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return list<T> the class's attributes of that type, its parents' included
     */
    private static function attributes(string $class, string $attribute): array
    {
        $found = [];
        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes($attribute) as $attr) {
                $found[] = $attr->newInstance();
            }
        }

        return $found;
    }
}
