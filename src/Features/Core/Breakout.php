<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use LogicException;
use Tey\Kit\Features\Core\Data\NavigationHeader;

/**
 * A breakout page leaves its parent's shared layout (a full-screen bench, an editor) and shows "Back to <parent>" instead of the breadcrumb trail. The strip
 * is derived from the trail's parent crumb, never written by hand, so it can't drift from the trail it replaces. A page breaks out through its ViewModel's
 * `breaksOut()` or its route's `kit.breakout` default (`->defaults('kit.breakout', true)`). The value 'with-trail' draws the strip and keeps the trail too.
 *
 * On one of a module's own tab pages (Registry::modulePage(), such as /billing/invoices under Admin) the tab row already says which module you are in, so the
 * strip skips every ancestor crumb that is a page of that module: it reads "Back to Admin". A deeper page that is not a tab (an invoice) keeps its tab.
 *
 * A page that stays inside its parent's layout (a tab, a settings section) is not a breakout: there the trail or the tabs already say where you are.
 * Two mistakes, a breakout with no parent crumb and a breakout inside a settings section, throw in local and testing. Elsewhere they draw no strip and the
 * trail stays, so a misdeclared page in production keeps its trail.
 */
final class Breakout
{
    public const WITH_TRAIL = 'with-trail';

    /** Whether the request's route declares the page a breakout. */
    public static function routeDeclares(Request $request): bool|string
    {
        $value = $request->route()?->defaults['kit.breakout'] ?? false;

        return $value === true || $value === self::WITH_TRAIL ? $value : false;
    }

    /** "Back to <parent>" for a breakout page: the trail's last crumb before the current one. Null (and a LogicException in local and testing) when it has none. */
    public static function header(Request $request, ?string $section = null, bool $trail = false): ?NavigationHeader
    {
        if ($section !== null) {
            return self::mistake('A breakout page leaves its parent\'s layout, so it has no settings section ('.$section.'). Drop the section, or keep the page in the section and drop breaksOut().');
        }
        $parent = self::parent($request);
        if ($parent === null) {
            return self::mistake('A breakout page needs a breadcrumb parent to go back to: give its route a parent crumb ('.($request->route()?->getName() ?? 'unnamed route').').');
        }

        return new NavigationHeader('Back to '.$parent['title'], $parent['url'], $trail);
    }

    /** @return array{title: string, url: string}|null */
    private static function parent(Request $request): ?array
    {
        $route = $request->route();
        if ($route === null || $route->getName() === null || ! Route::hasMacro('breadcrumbs')) {
            return null;
        }
        // gretel's crumbs serialize to {title, url, is_current_page}; they are objects until encoded.
        $crumbs = json_decode((string) json_encode($route->breadcrumbs()), true);
        $parents = array_values(array_filter(is_array($crumbs) ? $crumbs : [], fn (mixed $crumb): bool => is_array($crumb) && ! ($crumb['is_current_page'] ?? false)));
        $tabs = self::moduleTabPaths($request);
        if ($tabs !== null) {
            $parents = array_values(array_filter($parents, fn (array $crumb): bool => ! in_array('/'.trim((string) parse_url((string) ($crumb['url'] ?? ''), PHP_URL_PATH), '/'), $tabs, true)));
        }
        $parent = $parents === [] ? null : $parents[count($parents) - 1];

        return is_array($parent) && is_string($parent['url'] ?? null) && $parent['url'] !== '' ? ['title' => (string) $parent['title'], 'url' => $parent['url']] : null;
    }

    /**
     * The paths of every page of the module whose tab page the request is on (the longest page suffix its path ends with), or null on none. The base a suffix
     * hangs off (a workspace's /@slug) is the request path less that suffix, so each sibling tab's path is exact, never a prefix guess.
     *
     * @return ?list<string>
     */
    private static function moduleTabPaths(Request $request): ?array
    {
        $registry = app(Registry::class);
        $path = '/'.trim($request->path(), '/');
        $found = null;
        foreach ($registry->modules() as $module) {
            foreach ($registry->modulePages($module) as $page) {
                $suffix = trim($page['suffix'], '/');
                if ($suffix !== '' && str_ends_with($path, '/'.$suffix) && ($found === null || strlen($suffix) > strlen($found[1]))) {
                    $found = [$module, $suffix];
                }
            }
        }
        if ($found === null) {
            return null;
        }
        $base = substr($path, 0, -strlen('/'.$found[1]));

        return array_map(fn (array $page): string => '/'.trim($base.'/'.trim($page['suffix'], '/'), '/'), $registry->modulePages($found[0]));
    }

    private static function mistake(string $message): null
    {
        if (app()->environment('local', 'testing')) {
            throw new LogicException($message);
        }

        return null;
    }
}
