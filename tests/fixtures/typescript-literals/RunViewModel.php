<?php

namespace Tey\Kit\Fixtures\TypeScriptLiterals;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

#[TypeScript]
final class RunViewModel extends ViewModel
{
    /** @return 'queued'|'running' */
    public function status(): string
    {
        return 'queued';
    }

    /** @return list<array{id: int, state: 'ok'|'failed'}> */
    public function steps(): array
    {
        return [];
    }
}
