<?php

namespace Tey\Kit\Fixtures\NestedViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

#[TypeScript]
final class BoardViewModel extends ViewModel
{
    public function summary(): SummaryViewModel
    {
        return new SummaryViewModel(3);
    }

    public function pinned(): ?SummaryViewModel
    {
        return null;
    }

    /** @return list<RowViewModel> */
    public function rows(): array
    {
        return [new RowViewModel('a'), new RowViewModel('b')];
    }
}
