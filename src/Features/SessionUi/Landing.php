<?php

namespace Tey\Kit\Features\SessionUi;

use Tey\Kit\Features\Core\Registry;

/**
 * Where a session-ui flow sends a signed-in person when it ends (an accepted invitation, the last welcome step). With workspace-ui, the workspace page
 * the flow names. Without it (an auth-only app), the app's own home: `fortify.home`, which defaults to `kit.home` there.
 */
final class Landing
{
    public static function to(string $workspacePage): string
    {
        return app(Registry::class)->adopts('workspace-ui') ? $workspacePage : (string) config('fortify.home', '/');
    }
}
