<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;

/** What a widget is built for: its registered key, the request, the viewer and the workspace (whose tenant context is current while the widget runs). */
final class WidgetContext
{
    public function __construct(public readonly string $key, public readonly Request $request, public readonly Principal $principal, public readonly int $workspace) {}
}
