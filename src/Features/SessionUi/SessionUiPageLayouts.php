<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tey\Kit\Features\Core\Data\Breadcrumb;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\Data\PageLayout;
use Tey\Kit\Features\Core\ViewModels\PageLayouts;

/**
 * The layout of a page in the shell: the section navigation (from the registered settings sections), the heading, the full browser title and the
 * breadcrumb trail (gretel's, mapped once here). Everything is decided on the server for the current request, so a visit always carries the
 * right current item, heading, title and trail, and an empty navigation or trail clears what the previous page showed.
 */
final class SessionUiPageLayouts implements PageLayouts
{
    public function __construct(private readonly Request $request, private readonly SettingsNav $nav, private readonly PageTitle $titles) {}

    public function layout(string $title, ?string $section, ?NavigationHeader $navigationHeader = null): PageLayout
    {
        $breadcrumbs = $this->breadcrumbs();
        // A page with no trail (a guest page, an error, an ad-hoc page) names itself: its title is the document title's page label.
        if ($breadcrumbs === [] && $title !== '' && ! is_string($this->request->attributes->get('kit.page_title'))) {
            PageTitle::name($title, $this->request);
        }

        return new PageLayout(
            $section === null ? [] : $this->nav->groups($this->request, $section),
            $title,
            $this->titles->forRequest($this->request),
            $breadcrumbs,
            // Every account page (/me/*) is an escape to the workspace the viewer was in; any other page has a strip only when its ViewModel declares one (a deeper nested resource). No derivation from the trail.
            $navigationHeader ?? (PageShell::isAccount($this->request) ? PageShell::backToWorkspace($this->request) : null),
        );
    }

    /** @return list<Breadcrumb> */
    private function breadcrumbs(): array
    {
        $route = $this->request->route();
        if ($route === null || $route->getName() === null || ! Route::hasMacro('breadcrumbs')) {
            return [];
        }

        $crumbs = array_values($route->breadcrumbs()->jsonSerialize());

        return array_values(array_map(fn (array $crumb, int $index): Breadcrumb => new Breadcrumb((string) $crumb['title'], $crumb['url'], (bool) $crumb['is_current_page'], $index === 0 && self::isHome((string) $crumb['url'])), $crumbs, array_keys($crumbs)));
    }

    /** Whether a crumb's URL is the app's or a workspace's root (`/`, `/@slug`): by path, since gretel's URLs are absolute (`https://host/@slug`). */
    public static function isHome(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return preg_match('#^/(@[^/]+)?/?$#', $path) === 1;
    }
}
