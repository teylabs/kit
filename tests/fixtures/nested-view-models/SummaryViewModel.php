<?php

namespace Tey\Kit\Fixtures\NestedViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\ComponentViewModel;

#[TypeScript]
final class SummaryViewModel extends ComponentViewModel
{
    public function __construct(private int $open) {}

    public function open(): int
    {
        return $this->open;
    }
}
