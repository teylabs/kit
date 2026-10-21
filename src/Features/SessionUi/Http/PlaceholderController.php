<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ViewModels\PlaceholderViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * A page that is not built yet, answering at the real page's address. A member with the page's ability sees "Not built yet"; anyone else, a member without it as much as a
 * stranger, gets a 404, so the response never says the page exists. It takes no input and has nothing to submit.
 */
class PlaceholderController
{
    public function __invoke(Request $request, int $workspace): mixed
    {
        $definition = app(Registry::class)->placeholders()[(string) $request->route()?->defaults['placeholder']] ?? abort(404);
        $user = $request->user('web');
        abort_unless(Users::is($user), 404);
        $principal = new Principal((int) $user->getKey());

        return app(TenantBoundary::class)->run($workspace, function () use ($definition, $principal, $workspace, $request): mixed {
            if ($definition['ability'] !== null && ! $this->may($definition['ability'], $principal)) {
                abort(404);
            }
            $links = [];
            foreach ($definition['links'] as $link) {
                if ($link['ability'] !== null && ! $this->may($link['ability'], $principal)) {
                    continue;
                }
                try {
                    $links[] = new Action($link['label'], WorkspaceUrl::localise(route($link['route'], ['workspace' => WorkspaceUrl::slug($workspace)])), 'get');
                } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException|\Illuminate\Routing\Exceptions\UrlGenerationException) {
                    continue;
                }
            }

            return Inertia::render('Workspace/'.($definition['settings'] ? 'Settings/' : '').'Placeholder/Page', new PlaceholderViewModel($definition['title'], $definition['description'], $definition['milestone'], $links, $definition['settings'] ? null : $definition['width'], $definition['settings']));
        });
    }

    private function may(string $ability, Principal $principal): bool
    {
        try {
            app(Access::class)->authorize($principal, $ability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
