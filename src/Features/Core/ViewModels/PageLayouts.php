<?php

namespace Tey\Kit\Features\Core\ViewModels;

use Tey\Kit\Features\Core\Data\PageLayout;

/** Builds the layout of the page being rendered (navigation, title, document title, breadcrumbs) for the current request. */
interface PageLayouts
{
    /** @param  ?string  $section  the settings section navigation the page sits in, if any */
    public function layout(string $title, ?string $section): PageLayout;
}
