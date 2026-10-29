<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Mcp\Http\Middleware\BindConsentWorkspace;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Passport's authorize, where skipping consent never skips the workspace. Passport approves without the consent page when the client skips authorization or the person
 * already holds a live token for it with the scopes asked for; that path is `approveRequest`. Here it only goes ahead when the person has a live connection for this client
 * in the current workspace (the remembered one, or their only one): the code exchange then moves that connection onto the new token. Otherwise the consent page is shown,
 * as for a first connection (or, with `prompt=none`, Passport's `consent_required`).
 */
class KitAuthorizationController extends AuthorizationController
{
    /** The connection the next token for this person and client takes over, set when consent is skipped. */
    public static function reuseKey(int|string $userId, int|string $clientId): string
    {
        return 'kit.mcp.reuse.'.$userId.'.'.$clientId;
    }

    protected function approveRequest(AuthorizationRequestInterface $authRequest, ResponseInterface $psrResponse): Response
    {
        $request = request();
        $user = $this->guard->user();
        $clientId = (string) $authRequest->getClient()->getIdentifier();
        $workspace = WorkspaceUrl::currentMemberId($request);
        $connection = $workspace === null || $user === null ? null : DB::table('connections')->where('actor_id', $user->getAuthIdentifier())->where('oauth_client_id', $clientId)->where('workspace_id', $workspace)
            ->whereNotNull('oauth_token_id')->whereNull('revoked_at')->where('expires_at', '>', now())->orderByDesc('id')->value('id');
        if ($connection !== null && AuthorizationView::allows((int) $user->getAuthIdentifier(), $workspace)) {
            Cache::put(self::reuseKey($user->getAuthIdentifier(), $clientId), (int) $connection, BindConsentWorkspace::PENDING_TTL);

            return parent::approveRequest($authRequest, $psrResponse);
        }
        if ($request->string('prompt')->explode(' ')->contains('none')) {
            throw OAuthServerException::consentRequired($authRequest);
        }
        $request->session()->put('authToken', $authToken = Str::random());
        $request->session()->put('authRequest', serialize($authRequest));

        return app(AuthorizationViewResponse::class)->withParameters([
            'client' => $this->clients->find($clientId),
            'user' => $user,
            'scopes' => Passport::scopesFor(collect($authRequest->getScopes())->map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier())->unique()->all()),
            'request' => $request,
            'authToken' => $authToken,
        ])->toResponse($request);
    }
}
