<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Support\Str;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The error card that stands in for a widget that threw. It knows only the registration, never the failure. */
#[TypeScript]
final class FailedWidget extends Widget
{
    public function title(): string
    {
        return Str::headline(Str::afterLast($this->key(), '.'));
    }

    public function component(): string
    {
        return 'error';
    }

    public function state(): string
    {
        return 'error';
    }
}
