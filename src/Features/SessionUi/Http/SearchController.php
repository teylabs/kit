<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Search\Searches;
use Tey\Kit\Features\SessionUi\ViewModels\SearchViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Global search in a workspace: the full results page, or (asked for JSON, by the header palette) the first few results of each group. Behind
 * `search.read`, so the capability `workspace.search` must be on; rate limited per person (`Searches::throttle`, shared with the MCP tool). Never cached.
 */
final class SearchController
{
    public const PALETTE = 5;

    public const PAGE = 25;

    public function index(Request $request, int $workspace): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $term = $request->query('q');
        abort_if($term !== null && (! is_string($term) || mb_strlen($term) > Searches::MAX_TERM), 422);

        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace, $user, $term): mixed {
            $principal = new Principal((int) $user->getKey());
            app(Access::class)->authorize($principal, Searches::ABILITY);
            Searches::throttle($principal);
            $palette = $request->expectsJson() && ! $request->header('X-Inertia');
            $found = app(Searches::class)->run($principal, $workspace, (string) $term, $palette ? self::PALETTE : self::PAGE, $request);
            $response = $palette
                ? response()->json(['groups' => $found['groups'], 'partial' => $found['failed'] !== []])
                : Inertia::render('Workspace/Search/Page', new SearchViewModel('Search', trim((string) $term), WorkspaceUrl::to($workspace, 'search', 'session-ui', $request), $found['groups'], $found['failed'] !== []))->toResponse($request);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        });
    }
}
