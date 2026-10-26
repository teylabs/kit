<?php

namespace Tey\Kit\Features\Shell\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Which shell a page renders in. The server decides per route; the client switches on this and decides nothing. */
#[TypeScript]
enum ShellKind: string
{
    /** A signed-in page: the product, the workspace, navigation and the user menu. */
    case App = 'app';

    /** A guest page: the product and the footer, nothing about a viewer. */
    case Guest = 'guest';

    /** A signed-in page outside any workspace: accept an invitation, switch account, the welcome steps, the no-workspace page. */
    case Focus = 'focus';
}
