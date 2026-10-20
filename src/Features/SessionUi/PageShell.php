<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Data\ShellData;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

final class PageShell
{
    public function forRequest(Request $request): ShellData
    {
        $user = $request->user('web');
        $links = ['home' => '/me/workspaces', 'switcher' => '/me/workspaces', 'sign_in' => '/login', 'sign_out' => null];
        // The platform's identity: APP_NAME and its logomark. The workspace is separate (`switcher.current`) and never the mark.
        $product = ['name' => (string) config('app.name'), 'url' => '/me/workspaces', 'logomark' => config('kit.brand.logomark') ?: null];
        $env = app()->isProduction() ? null : app()->environment();
        if (! Users::is($user)) {
            return new ShellData($product, ['current' => null, 'choices' => []], [], null, $links, $env);
        }
        $links['sign_out'] = '/logout';
        $choices = [];
        $current = null;
        $slots = ['primary' => [], 'user-menu' => []];
        $concierge = app(Concierge::class);
        if ($concierge->account($user)->isActive() && $user->conciergeHasVerifiedEmail()) {
            foreach (Workspace::query()->orderBy('id')->get() as $workspace) {
                if (! $concierge->membership($user, $workspace)?->isActive()) {
                    continue;
                }
                $allowed = app(TenantBoundary::class)->run((int) $workspace->id, function () use ($workspace, $user, $request): array {
                    $allowed = ['primary' => [], 'user-menu' => []];
                    foreach (app(Registry::class)->navigationLinks() as $link) {
                        try {
                            app(Access::class)->authorize(new Principal((int) $user->id), $link['ability']);
                            $url = WorkspaceUrl::to($workspace, $link['suffix'], null, $request);
                            $allowed[$link['slot']][] = ['label' => $link['label'], 'url' => $url, 'current' => $this->active($request, $workspace, $link), 'group' => $link['group']];
                        } catch (RuntimeException $exception) {
                            if (! in_array($exception->getMessage(), ['workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'capability_unavailable', 'entitlement_required'], true)) {
                                throw $exception;
                            }
                        }
                    }

                    // Settings is one link, to the landing page, when the user can open any of its sections.
                    if (SettingsSections::visible('workspace', $workspace, new Principal((int) $user->id), $request) !== []) {
                        $allowed['primary'][] = ['label' => 'Settings', 'url' => WorkspaceUrl::to($workspace, 'settings', null, $request), 'current' => $this->active($request, $workspace, ['suffix' => 'settings', 'active' => null]), 'group' => 'workspace'];
                    }

                    return $allowed;
                });
                $isCurrent = (string) $request->route('workspace') === (string) $workspace->id;
                // Landing in a workspace means its first primary page.
                $choices[] = ['id' => (int) $workspace->id, 'name' => $workspace->name, 'url' => ($allowed['primary'][0] ?? $allowed['user-menu'][0] ?? null)['url'] ?? null, 'current' => $isCurrent];
                if ($isCurrent) {
                    $current = ['id' => (int) $workspace->id, 'name' => $workspace->name];
                    $slots = $allowed;
                }
            }
        }
        $identity = ['name' => $user->name, 'email' => $user->email, 'initials' => $this->initials((string) $user->name), 'items' => $slots['user-menu']];

        return new ShellData($product, ['current' => $current, 'choices' => $choices], $slots['primary'], $identity, $links, $env);
    }

    /** @param array{suffix: string, active: ?string} $link */
    private function active(Request $request, Workspace $workspace, array $link): bool
    {
        if ((string) $request->route('workspace') !== (string) $workspace->id) {
            return false;
        }
        // The page's path under the workspace: past the `@slug` segment in the full form, as is in the short form.
        $path = $request->decodedPath();
        $path = str_starts_with($path, '@') ? Str::after($path, '/') : $path;

        return Str::is($link['active'] !== null ? [$link['active']] : [$link['suffix'], $link['suffix'].'/*'], $path);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(count($parts) > 1 ? mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0] ?? '?', 0, 2));
    }
}
