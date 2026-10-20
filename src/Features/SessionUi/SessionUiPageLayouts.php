<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Data\Breadcrumb;
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

    public function layout(string $title, ?string $section): PageLayout
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
        );
    }

    /** @return list<Breadcrumb> */
    private function breadcrumbs(): array
    {
        $route = $this->request->route();
        if ($route === null || $route->getName() === null || ! \Illuminate\Routing\Route::hasMacro('breadcrumbs')) {
            return [];
        }

        return array_values(array_map(fn (array $crumb): Breadcrumb => new Breadcrumb((string) $crumb['title'], $crumb['url'], (bool) $crumb['is_current_page']), $route->breadcrumbs()->jsonSerialize()));
    }
}
