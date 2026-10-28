<?php

namespace Tey\Kit\Tests\Fixtures\PropOrder;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChildPage extends BasePage
{
    use Badges;

    public function zebra(): int
    {
        return 1;
    }

    public function subtitle(): string
    {
        return 'Child subtitle';
    }

    public function alpha(): bool
    {
        return true;
    }
}
