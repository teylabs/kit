<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** What a widget with nothing to show says, and the one action that fills it. */
#[TypeScript]
final class WidgetEmptyData extends Data
{
    public function __construct(public string $title, public string $description, public ?Action $action = null) {}
}
