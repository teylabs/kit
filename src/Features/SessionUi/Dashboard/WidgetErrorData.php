<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** A widget that could not load: a fixed message (never exception text) and a retry. */
#[TypeScript]
final class WidgetErrorData extends Data
{
    public function __construct(public string $message, public ?Action $retry = null) {}
}
