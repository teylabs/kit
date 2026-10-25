<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** What a page tells its layout: the section navigation, the heading, the full browser title (composed on the server) and the breadcrumb trail (root first, the last current). */
#[TypeScript]
final class PageLayout extends Data
{
    /**
     * @param  list<NavGroup>  $navigation  one group with a null label when ungrouped; empty when the page has none
     * @param  list<Breadcrumb>  $breadcrumbs  empty when the page has none
     * @param  ?NavigationHeader  $navigationHeader  a nested page's way up one level (a strip under the header, scrolling with the page); null when the page sets none
     */
    public function __construct(public array $navigation, public string $title, public string $documentTitle, public array $breadcrumbs, public ?NavigationHeader $navigationHeader = null) {}
}
