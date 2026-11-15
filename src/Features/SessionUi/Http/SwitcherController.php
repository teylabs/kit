<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PageShell;

/**
 * `GET /me/switcher`: the page each of the signed-in person's workspaces opens on, which the shell's switcher (and the workspace list) loads when it opens.
 * The shell sends only the list (`switcher.choices`: id, name, slug); each workspace's page is decided here, inside that workspace, link by link, exactly as
 * the shell decides the one it names (PageShell::landings()). Only the person's own active memberships; `url` null where they may open nothing (unavailable).
 */
class SwitcherController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Users::is($request->user('web')), 401);
        $landings = app(PageShell::class)->landings($request);

        return response()->json(['workspaces' => array_map(fn (int $id, ?string $url): array => ['id' => $id, 'url' => $url], array_keys($landings), $landings)])->header('Cache-Control', 'no-store, private');
    }
}
