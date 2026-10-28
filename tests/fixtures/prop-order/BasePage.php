<?php

namespace Tey\Kit\Tests\Fixtures\PropOrder;

use Tey\Kit\Features\Core\ViewModels\ViewModel;

abstract class BasePage extends ViewModel
{
    public function title(): string
    {
        return 'Base';
    }

    public function subtitle(): string
    {
        return 'Base subtitle';
    }
}
