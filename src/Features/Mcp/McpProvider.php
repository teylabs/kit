<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\ClientRepository as BridgeClientRepository;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Events\AccessTokenRevoked;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Passport;
use RuntimeException;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Bridge\LoopbackClientRepository;
use Tey\Kit\Features\Mcp\Console\ClientsCommand;
use Tey\Kit\Features\Mcp\Http\AuthorizationView;
use Tey\Kit\Features\Mcp\Http\KitAuthorizationController;
use Tey\Kit\Features\Mcp\DescriptorResource;
use Tey\Kit\Features\Mcp\Http\Middleware\AdvertiseScope;
use Tey\Kit\Features\Mcp\Listeners\RebindConnection;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;

final class McpProvider extends ServiceProvider implements Contributes
{
    public const string REGISTRATION_LIMITER = 'kit-mcp-register';

    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/connections.php', 'web', $routes)
            ->routes(__DIR__.'/routes/mcp.php', null, $routes)
            ->scopes(['mcp:use' => 'Act as you in the workspaces you approve, with what your role there allows', WorkspaceConnection::ACTS_AS_ME => 'Act as you in the workspaces you approve'])
            // Revoking another member's connection: owners and admins by default, its own permission so it stays apart from settings.write.
            ->abilities([WorkspaceConnection::MANAGE => 'workspace.settings'])
            ->permission('workspace.connections.manage', ['owner', 'admin'], null, [WorkspaceConnection::MANAGE])
            ->connectionGuard(WorkspaceConnection::guard(...))
            ->capabilityToolCheck(Capability::check(...))
            ->mcpResourceCheck(Capability::checkResource(...), fn (array $descriptor): DescriptorResource => new DescriptorResource($descriptor))
            ->explicitGrantsUsing(fn (string $tool): ?array => ($capability = Capability::of($tool)) !== null && $capability->explicit() && $capability->scope !== null ? ['scope' => $capability->scope, 'explains' => (string) $capability->explains] : null)
            ->errors(['connection_denied' => 403, 'connection_intent_conflict' => 409, 'connection_revoked' => 409, 'connection_active' => 409, 'confirmation_invalid' => 409, 'scope_required' => 403]);
        // The AI assistants page is hidden until the MCP connection rework (one address, workspace picked at consent): off by default, so it is neither in the settings nav nor reachable.
        if (config('kit.mcp.ui', false)) {
            $registry->settingsSection('workspace', 'connections', 'AI assistants', 'Connect AI assistants like Claude or ChatGPT to this workspace.', 'settings.read', 'Workspace', 20, 'plug');
        }
    }

    /**
     * Who runs the OAuth consent: 'kit' (the kit's Authorize page, workspace picked there, the kit's connection row) or 'host' (an app with its own consent page and
     * grants; the kit leaves Passport's authorize and approve as Passport ships them). Any other value is refused rather than guessed.
     */
    public static function consent(): string
    {
        $mode = config('kit.mcp.consent') ?? 'kit';
        if (! in_array($mode, ['kit', 'host'], true)) {
            throw new RuntimeException('kit.mcp.consent must be \'kit\' or \'host\', not '.var_export($mode, true).'.');
        }

        return $mode;
    }

    /** Whether assistants may register themselves as OAuth clients (`kit.mcp.registration`, RFC 7591). Off by default: the route does not exist. */
    public static function registration(): bool
    {
        return filter_var(config('kit.mcp.registration', false), FILTER_VALIDATE_BOOL);
    }

    public function register(): void
    {
        // Passport's client bridge with loopback port matching for localhost, so a native client's per-session port passes exact redirect matching (RFC 8252 §7.3).
        $this->app->bind(BridgeClientRepository::class, LoopbackClientRepository::class);
        // Passport's authorize, with the kit's rule for when consent may be skipped (see KitAuthorizationController). A host running its own consent keeps Passport's.
        if (self::consent() === 'kit') {
            $this->app->bind(AuthorizationController::class, KitAuthorizationController::class);
        }
        // One listener instance per request, so the access tokens a refresh revokes are seen by the token it issues next, and never by a later request.
        $this->app->scoped(RebindConnection::class);
        $this->app->when(KitAuthorizationController::class)->needs(StatefulGuard::class)->give(fn () => Auth::guard(config('passport.guard', null)));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 3).'/resources/views', 'kit');
        Passport::tokensCan(app(Registry::class)->tokenScopes());
        Passport::tokensExpireIn(now()->addHour());
        if (self::consent() === 'kit') {
            Passport::authorizationView(fn (array $parameters) => app(AuthorizationView::class)($parameters));
        }
        Event::listen(AccessTokenRevoked::class, [RebindConnection::class, 'revoked']);
        Event::listen(AccessTokenCreated::class, RebindConnection::class);
        // Registration is per IP: a registering assistant sends one request per fresh connection, so a burst is a scan, not a person.
        RateLimiter::for(self::REGISTRATION_LIMITER, fn (Request $request) => Limit::perMinute(max(1, (int) config('kit.mcp.registration_per_minute', 10)))->by('kit-mcp-register|'.(string) $request->ip()));
        // The scope an assistant should ask for, added to laravel/mcp's 401 challenge; prepended so it runs outside the middleware that writes the challenge.
        $this->callAfterResolving(KernelContract::class, function (mixed $kernel): void {
            if ($kernel instanceof HttpKernel) {
                $kernel->prependMiddleware(AdvertiseScope::class);
            }
        });
        $this->commands([ClientsCommand::class]);
    }
}
