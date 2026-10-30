<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A navigator item's thumbnail: the image `src` (a URL the page can load, such as a rendered slide preview) and its `alt`. The label already names the item, so
 * `alt` is an empty string when the image only shows what the label says (decorative); give it text only when the image adds something the label doesn't.
 */
#[TypeScript]
final class WorkbenchThumbnail extends Data
{
    public function __construct(public string $src, public string $alt) {}
}
