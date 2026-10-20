<?php

namespace Tey\Kit\Features\Core\ViewModels;

use Tey\Kit\Features\Core\Data\PageLayout;

/** The layout of an app with no shell: just the heading and the document title. */
final class PlainPageLayouts implements PageLayouts
{
    public function layout(string $title, ?string $section): PageLayout
    {
        return new PageLayout([], $title, $title.' · '.config('app.name'), []);
    }
}
