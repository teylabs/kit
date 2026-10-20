<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;
use Tey\Kit\Features\SessionUi\Policies\UserAvatarPolicy;
use Tey\Kit\Features\SessionUi\ViewModels\ErrorViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\LoginViewModel;

final class SessionUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        // A user with exactly one reachable workspace has nothing to choose on the list: it opens that workspace at its first page.
        $registry->routes(__DIR__.'/routes/sections.php', 'web', $routes)
            ->navigation('dashboard.read', 'Dashboard', 'dashboard', order: -10)
            ->abilities(['dashboard.read' => 'workspace.dashboard', 'members.manage' => 'workspace.settings'])
            ->capabilities(['workspace.dashboard'])
            ->settingsSection('workspace', 'people', 'People', 'Who belongs to this workspace, their roles, and who is invited.', 'members.manage', 'Workspace', 0, 'users')
            ->settingsSection('personal', 'profile', 'Profile', 'Your name and photo.', null, 'Account', -20, 'user');
        $registry->workspaceOpener(function (Request $request): ?string {
            $reachable = array_values(array_filter(app(PageShell::class)->forRequest($request)->switcher['choices'], fn (array $choice) => $choice['url'] !== null));

            return count($reachable) === 1 ? $reachable[0]['url'] : null;
        });
        $registry->errorPages(function (string $code, int $status, ?string $message, Request $request) {
            // The error names its page once: the heading and the document title are the same label.
            $label = match ($status) {
                404 => 'Page not found',
                403 => 'Not allowed',
                409 => 'Out of date',
                422 => 'Check your input',
                default => 'Something went wrong',
            };
            PageTitle::name($label, $request);

            return Inertia::render('Workspace/Error/Page', new ErrorViewModel($label, $status, $message ?? 'This request is unavailable. Your access or the resource may have changed.', '/me/workspaces', 'Return to your workspaces'))->toResponse($request)->setStatusCode($status);
        });
    }

    public function register(): void
    {
        Defaults::apply($this->app, 'fortify', 'laravel/fortify');
        Defaults::apply($this->app, 'inertia', 'inertiajs/inertia-laravel');
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in')));
        Gate::policy(UserAvatar::class, UserAvatarPolicy::class);
        // The link in an invitation (mailed, or shown to the person who invited): the page that explains it and lets the invited person in.
        if (class_exists(\Tey\Concierge\Concierge::class)) {
            $this->app->make(\Tey\Concierge\Concierge::class)->invitationUrlUsing(fn ($invitation, string $token): string => url('/invitations/'.$token));
        }
        Relation::morphMap(['kit-user-avatar' => UserAvatar::class]);
    }
}
