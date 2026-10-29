<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** How many grid columns a widget spans: `md` of 6 (tablet), `lg` of 12 (desktop), within its region. `rows`: grid rows from tablet up (1–4). Phones are always one column. */
#[TypeScript]
final class WidgetSpanData extends Data
{
    public function __construct(public int $md = 6, public int $lg = 6, public int $rows = 1)
    {
        if ($md < 1 || $md > 6 || $lg < 1 || $lg > 12 || $rows < 1 || $rows > 4) {
            throw new \InvalidArgumentException('A widget spans 1–6 columns at md, 1–12 at lg and 1–4 rows.');
        }
    }
}
