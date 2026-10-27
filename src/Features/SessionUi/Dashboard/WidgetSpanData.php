<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** How many grid columns a widget spans: `md` of 6 (tablet), `lg` of 12 (desktop). Phones are always one column. */
#[TypeScript]
final class WidgetSpanData extends Data
{
    public function __construct(public int $md = 6, public int $lg = 6)
    {
        if ($md < 1 || $md > 6 || $lg < 1 || $lg > 12) {
            throw new \InvalidArgumentException('A widget spans 1–6 columns at md and 1–12 at lg.');
        }
    }
}
