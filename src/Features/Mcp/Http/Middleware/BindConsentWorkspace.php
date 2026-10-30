<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Mcp\Http\AuthorizationView;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;

/**
 * Runs around Passport's approve. The chosen `workspace` must be one the person is an active member of and may open a connection in; anything else is refused with 403
 * before Passport issues a code. When Passport answers with a code, a pending connection is made in that workspace for this person and client, with no token yet: the
 * first access token the client gets for the person (the code exchange) takes it (`RebindConnection`). An unused pending connection lapses with the code.
 */
class BindConsentWorkspace
{
    /** How long a pending connection waits for its code to be exchanged: Passport's authorization codes live ten minutes. */
    public const PENDING_TTL = 600;

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        $workspace = filter_var($request->input('workspace'), FILTER_VALIDATE_INT);
        abort_unless($user !== null && $workspace !== false && AuthorizationView::allows((int) $user->getAuthIdentifier(), $workspace), 403);
        $pending = $request->session()->get('authRequest');
        $authRequest = is_string($pending) ? unserialize($pending, ['allowed_classes' => [AuthorizationRequest::class, \Laravel\Passport\Bridge\Client::class, \Laravel\Passport\Bridge\Scope::class, \Laravel\Passport\Bridge\User::class]]) : null;
        if (! $authRequest instanceof AuthorizationRequest) {
            // No request to approve: Passport refuses it itself.
            return $next($request);
        }
        $requested = array_map(fn ($scope): string => $scope->getIdentifier(), $authRequest->getScopes());
        // An explicit scope in the OAuth request is not a grant: it is dropped here and comes back only as a ticked box below.
        $explicit = app(\Tey\Kit\Features\Core\Registry::class)->explicitScopes();
        $narrow = array_values(array_diff($requested, ['mcp:use', WorkspaceConnection::ACTS_AS_ME, ...array_keys($explicit)]));
        $scopes = $narrow === [] ? [WorkspaceConnection::ACTS_AS_ME] : $narrow;
        sort($scopes);
        // A narrow request must include the connection ability, as an approval on the AI assistants page must; each narrow scope is checked like the base one.
        abort_if($narrow !== [] && (! in_array(WorkspaceConnection::baseAbility(), $narrow, true) || array_diff($narrow, WorkspaceConnection::grantableScopes())), 403);
        foreach ($narrow as $scope) {
            abort_unless(AuthorizationView::allows((int) $user->getAuthIdentifier(), $workspace, $scope), 403);
        }
        // Explicit grants come only from the ticked boxes, never from the OAuth scope parameter: each must be an explicit scope the person may hold in the chosen workspace.
        $granted = $request->input('grants', []);
        abort_unless(is_array($granted) && array_is_list($granted), 403);
        foreach ($granted as $scope) {
            abort_unless(is_string($scope) && isset($explicit[$scope]) && AuthorizationView::allows((int) $user->getAuthIdentifier(), $workspace, $scope), 403);
        }
        $scopes = array_values(array_unique([...array_diff($scopes, array_keys($explicit)), ...$granted]));
        sort($scopes);

        $response = $next($request);
        $location = $response instanceof RedirectResponse || $response->isRedirect() ? (string) $response->headers->get('Location') : '';
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        if (! in_array('mcp:use', $requested, true) || ! is_string($query['code'] ?? null)) {
            return $response;
        }
        $id = DB::table('connections')->insertGetId(['workspace_id' => $workspace, 'actor_id' => (int) $user->getAuthIdentifier(), 'oauth_token_id' => null, 'oauth_client_id' => (string) $authRequest->getClient()->getIdentifier(),
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR), 'expires_at' => now()->addSeconds(self::PENDING_TTL)]);
        app(Audit::class)->write((int) $user->getAuthIdentifier(), $workspace, 'workspace.connection.approved', ['connection_id' => $id, 'client_id' => $authRequest->getClient()->getIdentifier(), 'scopes' => $scopes, 'grants' => array_values($granted)]);

        return $response;
    }
}
