<?php

namespace Tey\Kit\Features\Shell;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * What builds the shared `shell` prop for a request. The shell feature shares it and binds PlainShell; session-ui binds its PageShell
 * (the focus shell, avatars and, with workspaces, the switcher and navigation) in its place. One producer, shared once.
 */
interface ShellSource
{
    public function forRequest(Request $request): Data;
}
