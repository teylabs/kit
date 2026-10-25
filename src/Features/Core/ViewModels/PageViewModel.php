<?php

namespace Tey\Kit\Features\Core\ViewModels;

use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\Data\PageLayout;

/**
 * A page's ViewModel: a `title()` (the heading and the base of the document title) and a `layout()`, which carries the page's section navigation, heading,
 * document title and breadcrumbs. Pages declare no layout of their own: the nearest folder `Layout.vue` renders `layout.navigation`. Which section
 * navigation a page belongs to is `section()` (`workspace` or `personal` for the settings sections; null for none). Per-page actions and `can`
 * flags stay on the page's own methods.
 */
abstract class PageViewModel extends ViewModel
{
    abstract public function title(): string;

    /** The settings section navigation this page sits in: `workspace`, `personal`, or null. */
    protected function section(): ?string
    {
        return null;
    }

    /** The way up one level, drawn as a strip under the header: null (the default) for a page with none, which most are. */
    protected function navigationHeader(): ?NavigationHeader
    {
        return null;
    }

    public function layout(): PageLayout
    {
        return app(PageLayouts::class)->layout($this->title(), $this->section(), $this->navigationHeader());
    }
}
