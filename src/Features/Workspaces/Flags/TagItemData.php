<?php

namespace Tey\Kit\Features\Workspaces\Flags;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One tag as the kit's TagList shows it: a flag the system raised (`kind` flag) or a person's tag (`kind` tag). The label carries the meaning; the tone only marks it. */
#[TypeScript]
class TagItemData extends Data
{
    /** @param  'flag'|'tag'  $kind */
    public function __construct(public string $key, public string $label, public string $kind, public string $tone = 'neutral', public ?string $tooltip = null, public ?string $href = null) {}
}
