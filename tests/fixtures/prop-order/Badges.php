<?php

namespace Tey\Kit\Tests\Fixtures\PropOrder;

trait Badges
{
    public function badge(): string
    {
        return 'new';
    }

    /** Overrides the parent's title from the trait. */
    public function title(): string
    {
        return 'Badged';
    }
}
