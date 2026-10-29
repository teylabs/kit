<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a widget's card is drawn. The defaults are the plain card. `header: false` hides the visible header (its title stays as the section's accessible name);
 * `padding` is the body's: `default`, `none`, or `list` (vertical only, for rows that pad themselves); `surface: none` drops the card's border, background and
 * shadow so the body draws its own; `hideWhenEmpty` removes an empty widget's cell instead of showing its empty state. An error always shows, in a plain card.
 */
#[TypeScript]
final class WidgetChromeData extends Data
{
    public function __construct(public bool $header = true, public string $padding = 'default', public string $surface = 'card', public bool $hideWhenEmpty = false)
    {
        if (! in_array($padding, ['default', 'none', 'list'], true) || ! in_array($surface, ['card', 'none'], true)) {
            throw new \InvalidArgumentException('A widget\'s padding is default, none or list, and its surface card or none.');
        }
    }
}
