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

final class PageShell
{
    public function forRequest(Request $request): ShellData
    {
        $user = $request->user('web');
        $links = ['home' => '/workspaces', 'switcher' => '/workspaces', 'sign_in' => '/login', 'sign_out' => null];
        $product = ['name' => (string) config('app.name'), 'url' => '/workspaces'];
        $env = app()->isProduction() ? null : app()->environment();
        if (! Users::is($user)) {
            return new ShellData($product, ['current' => null, 'choices' => []], [], null, null, $links, $env);
        }
        $links['sign_out'] = '/logout';
        $choices = [];
        $current = null;
        $slots = ['primary' => [], 'settings' => [], 'user-menu' => []];
        $concierge = app(Concierge::class);
        if ($concierge->account($user)->isActive() && $user->conciergeHasVerifiedEmail()) {
            foreach (Workspace::query()->orderBy('id')->get() as $workspace) {
                if (! $concierge->membership($user, $workspace)?->isActive()) {
                    continue;
                }
                $allowed = app(TenantBoundary::class)->run((int) $workspace->id, function () use ($workspace, $user, $request): array {
                    $allowed = ['primary' => [], 'settings' => [], 'user-menu' => []];
                    foreach (app(Registry::class)->navigationLinks() as $link) {
                        try {
                            app(Access::class)->authorize(new Principal((int) $user->id), $link['ability']);
                            $url = '/workspaces/'.$workspace->id.'/'.$link['suffix'];
                            $allowed[$link['slot']][] = ['label' => $link['label'], 'url' => $url, 'current' => $this->active($request, (int) $workspace->id, $link), 'group' => $link['group']];
                        } catch (RuntimeException $exception) {
                            if (! in_array($exception->getMessage(), ['workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'capability_unavailable', 'entitlement_required'], true)) {
                                throw $exception;
                            }
                        }
                    }

                    return $allowed;
                });
                $isCurrent = (string) $request->route('workspace') === (string) $workspace->id;
                // Landing in a workspace means its first primary page; settings only when it has nothing else.
                $choices[] = ['id' => (int) $workspace->id, 'name' => $workspace->name, 'url' => ($allowed['primary'][0] ?? $allowed['settings'][0] ?? $allowed['user-menu'][0] ?? null)['url'] ?? null, 'current' => $isCurrent];
                if ($isCurrent) {
                    $current = ['id' => (int) $workspace->id, 'name' => $workspace->name];
                    $slots = $allowed;
                }
            }
        }
        $settings = $slots['settings'] === [] ? null : ['label' => 'Settings', 'current' => in_array(true, array_column($slots['settings'], 'current'), true), 'items' => $slots['settings']];
        $identity = ['name' => $user->name, 'email' => $user->email, 'initials' => $this->initials((string) $user->name), 'items' => $slots['user-menu']];

        return new ShellData($product, ['current' => $current, 'choices' => $choices], $slots['primary'], $settings, $identity, $links, $env);
    }

    /** @param array{suffix: string, active: ?string} $link */
    private function active(Request $request, int $workspace, array $link): bool
    {
        $path = Str::after('/'.$request->path(), '/workspaces/'.$workspace.'/');

        return Str::is($link['active'] !== null ? [$link['active']] : [$link['suffix'], $link['suffix'].'/*'], $path);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(count($parts) > 1 ? mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0] ?? '?', 0, 2));
    }
}
