<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The measure of a page's content column: the widths `<Page>` takes. A list or index page is `Full`; a form or settings page a narrower one. */
#[TypeScript]
enum PageWidth: string
{
    case Xs = 'xs';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case Full = 'full';
}
