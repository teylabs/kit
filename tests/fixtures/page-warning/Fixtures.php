<?php

namespace Tey\Kit\Tests\Fixtures\PageWarning;

use Tey\Kit\Features\Core\ViewModels\ComponentViewModel;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

final class PlainPage extends ViewModel
{
    public function title(): string
    {
        return 'Plain';
    }

    public function widget(): WidgetCard
    {
        return new WidgetCard;
    }
}

final class TypedPage extends PageViewModel
{
    // The layout prop needs the full app (laravel-data, the shell); the check is about the class, not the layout.
    protected array $hidden = ['layout'];

    public function title(): string
    {
        return 'Typed';
    }

    public function widget(): WidgetCard
    {
        return new WidgetCard;
    }

    public function child(): ChildProps
    {
        return new ChildProps;
    }
}

final class WidgetCard extends ComponentViewModel
{
    public function label(): string
    {
        return 'Card';
    }
}

final class ChildProps extends ViewModel
{
    public function count(): int
    {
        return 1;
    }
}

final class PublicPage extends ViewModel
{
    protected bool $standalone = true;

    public function title(): string
    {
        return 'Public';
    }
}
