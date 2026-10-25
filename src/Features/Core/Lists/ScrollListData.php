<?php

namespace Tey\Kit\Features\Core\Lists;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The payload of one ScrollList window: `state` is `ready`, `empty` or `error`. Count-free: there is no total. */
#[TypeScript]
final class ScrollListData extends Data
{
    /** @param list<ListItemData> $data */
    public function __construct(public string $key, public string $title, public string $state, public array $data, public ?ListEmptyData $empty = null, public ?ListErrorData $error = null) {}
}
