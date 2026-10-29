<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Mcp\ViewModels\AuthorizeViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Tey\Kit\Features\Workspaces\Workspaces;

/** Passport's authorization view: the consent page, as an Inertia page. Passport has already refused an unknown client or an unregistered redirect_uri before it gets here. */
final class AuthorizationView
{
    /** @param  array{client: Client, user: Authenticatable, scopes: list<\Laravel\Passport\Scope>, request: Request, authToken: string}  $parameters */
    public function __invoke(array $parameters): Response
    {
        $request = $parameters['request'];
        $user = $parameters['user'];
        $workspaces = self::workspaces($user);
        $current = WorkspaceUrl::currentMemberId($request);
        $ids = array_column($workspaces, 'id');
        $labels = Passport::$scopes;
        $scopes = array_map(fn ($scope): array => ['key' => (string) $scope->id, 'label' => (string) ($labels[$scope->id] ?? $scope->id)], $parameters['scopes']);

        $model = new AuthorizeViewModel('Connect '.$parameters['client']->name, (string) $parameters['client']->name, $scopes, $workspaces, in_array($current, $ids, true) ? $current : ($ids[0] ?? null),
            route('passport.authorizations.approve'), route('passport.authorizations.deny'), $parameters['authToken'], (string) $request->session()->token());

        return Inertia::render('Mcp/Authorize/Page', $model)->toResponse($request);
    }

    /** @return list<array{id: int, name: string}> The workspaces the person is an active member of and may open a connection in (the connection ability). */
    public static function workspaces(Authenticatable $user): array
    {
        $allowed = [];
        foreach (Workspaces::query()->memberOf($user)->orderBy('name')->get() as $workspace) {
            if (self::allows((int) $user->getAuthIdentifier(), (int) $workspace->getKey())) {
                $allowed[] = ['id' => (int) $workspace->getKey(), 'name' => (string) $workspace->name];
            }
        }

        return $allowed;
    }

    /** Whether the person may bind a connection to the workspace: Access's full check of the ability (the connection ability by default), run inside it. Any refusal is no. */
    public static function allows(int $userId, int $workspaceId, ?string $ability = null): bool
    {
        try {
            app(TenantBoundary::class)->run($workspaceId, fn () => app(Access::class)->authorize(new Principal($userId), $ability ?? WorkspaceConnection::baseAbility()));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
