<?php

namespace Tey\Kit\Features\SessionUi\Http;

/** The root of the app: "/" sends a signed-in user to where the app lands them (the workspace list, which opens a lone workspace) and a guest to sign in. An app can serve "/" itself. */
class HomeController
{
    public function __invoke(): mixed
    {
        return redirect('/me/workspaces');
    }
}
