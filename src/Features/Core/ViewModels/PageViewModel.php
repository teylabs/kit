<?php

namespace Tey\Kit\Features\Core\ViewModels;

use Tey\Kit\Features\Core\Breakout;
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

    /** The way up one level, drawn as a strip under the header in place of the trail: null (the default) for a page with none, which most are. A breakout page derives it (breaksOut()). */
    protected function navigationHeader(): ?NavigationHeader
    {
        return null;
    }

    /**
     * Whether this page breaks out of its parent's shared layout (a full-screen bench or editor): the shell then shows "Back to <parent>", derived from the
     * trail's parent crumb, in place of the trail (Breakout). By default, the route's `kit.breakout` default. A page inside its parent's layout (a tab, a
     * settings section) does not break out.
     */
    protected function breaksOut(): bool
    {
        return Breakout::routeDeclares(request());
    }

    public function layout(): PageLayout
    {
        $header = $this->navigationHeader() ?? ($this->breaksOut() ? Breakout::header(request(), $this->section()) : null);

        return app(PageLayouts::class)->layout($this->title(), $this->section(), $header);
    }
}
