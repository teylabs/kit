<?php

namespace Tey\Kit\Fixtures\NestedViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\ComponentViewModel;

#[TypeScript]
final class RowViewModel extends ComponentViewModel
{
    public function __construct(private string $label) {}

    public function label(): string
    {
        return $this->label;
    }
}
