<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Data\FocusShellData;
use Tey\Kit\Features\SessionUi\Data\GuestShellData;
use Tey\Kit\Features\Shell\Data\ShellData;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

final class PageShell
{
    /** The app shell for a signed-in viewer; a guest gets the GuestShell: the product and the footer, nothing about a viewer, a workspace or what they may do. */
    /** The workspaces the viewer can open, in the switcher's shape (empty for a guest). */
    public function choices(Request $request): array
    {
        $this->forRequest($request);

        return $request->attributes->get('kit.shell_choices', []);
    }

    /** An account page (/me/*): outside any workspace, so the bar shows the account, not a workspace. The server decides; the client renders `shell.mode`. */
    public static function isAccount(Request $request): bool
    {
        $name = (string) $request->route()?->getName();

        return str_starts_with($name, 'me.') || $name === 'workspaces.index';
    }

    /** Up one level from an account page: back to the last-used workspace, named for it. Null without one. */
    public static function backToWorkspace(Request $request): ?NavigationHeader
    {
        $shell = app(self::class);
        $lastId = WorkspaceUrl::currentMemberId($request);
        $last = $lastId === null ? null : collect($shell->choices($request))->firstWhere('id', $lastId);

        return $last === null || $last['url'] === null ? null : new NavigationHeader('Back to '.$last['name'], $last['url']);
    }

    public function forRequest(Request $request): ShellData|GuestShellData|FocusShellData
    {
        $user = $request->user('web');
        $links = ['home' => '/me/workspaces', 'switcher' => '/me/workspaces', 'sign_in' => '/login', 'sign_out' => null];
        // The platform's identity: APP_NAME and its logomark. The workspace is separate (`switcher.current`) and never the mark.
        $product = ['name' => (string) config('app.name'), 'url' => '/me/workspaces', 'logomark' => config('kit.brand.logomark') ?: null, 'logomark_dark' => config('kit.brand.logomark_dark') ?: null, 'wordmark' => config('kit.brand.wordmark') ?: null, 'wordmark_dark' => config('kit.brand.wordmark_dark') ?: null, 'lockup' => (bool) config('kit.brand.lockup')];
        if (! Users::is($user)) {
            return new GuestShellData($product, app(Registry::class)->webFooter());
        }
        if (app(Registry::class)->isFocusRoute($request->route()?->getName())) {
            return $this->focus($request, $user, $product);
        }
        $links['sign_out'] = '/logout';
        $choices = [];
        $current = null;
        $settings = [];
        $slots = ['primary' => [], 'user-menu' => []];
        $concierge = app(Concierge::class);
        if (RequestMemo::account($user)->isActive() && $user->conciergeHasVerifiedEmail()) {
            // Only the viewer's own active memberships, in one query: never every workspace.
            foreach (MemberWorkspaces::workspaces($user) as $workspace) {
                $allowed = app(TenantBoundary::class)->run((int) $workspace->id, function () use ($workspace, $user, $request): array {
                    $allowed = ['primary' => [], 'user-menu' => [], 'settings' => null];
                    foreach (app(Registry::class)->navigationLinks() as $link) {
                        try {
                            app(Access::class)->authorize(new Principal((int) $user->id), $link['ability']);
                            $url = WorkspaceUrl::to($workspace, $link['suffix'], null, $request);
                            $allowed[$link['slot']][] = ['label' => $link['label'], 'url' => $url, 'current' => $this->active($request, $workspace, $link), 'group' => $link['group']];
                        } catch (RuntimeException $exception) {
                            if (! in_array($exception->getMessage(), ['workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'capability_unavailable', 'entitlement_unavailable', 'entitlement_required'], true)) {
                                throw $exception;
                            }
                        }
                    }

                    // Settings is one link, to the landing page, when the user can open any of its sections.
                    if (SettingsSections::visible('workspace', $workspace, new Principal((int) $user->id), $request) !== []) {
                        $allowed['settings'] = WorkspaceUrl::to($workspace, 'settings', null, $request);
                        $allowed['primary'][] = ['label' => 'Settings', 'url' => WorkspaceUrl::to($workspace, 'settings', null, $request), 'current' => $this->active($request, $workspace, ['suffix' => 'settings', 'active' => null]), 'group' => 'workspace'];
                    }

                    return $allowed;
                });
                $isCurrent = (string) $request->route('workspace') === (string) $workspace->id;
                // Landing in a workspace means its first primary page.
                $settings[(int) $workspace->id] = $allowed['settings'];
                $choices[] = ['id' => (int) $workspace->id, 'name' => $workspace->name, 'url' => ($allowed['primary'][0] ?? $allowed['user-menu'][0] ?? null)['url'] ?? null, 'current' => $isCurrent];
                if ($isCurrent) {
                    $current = ['id' => (int) $workspace->id, 'name' => $workspace->name];
                    $slots = $allowed;
                }
            }
        }
        $identity = ['name' => $user->name, 'email' => $user->email, 'initials' => $this->initials((string) $user->name), 'avatar_url' => ($avatar = Avatars::urls((int) $user->getKey()))['url'] ?? null, 'avatar_url_2x' => $avatar['url_2x'] ?? null, 'items' => $this->accountItems($user, $request)];

        $request->attributes->set('kit.shell_choices', $choices);
        // The workspace is always named in the bar: the current one, or the only one there is. The switcher exists only with two or more.
        $lastId = $current !== null ? $current['id'] : WorkspaceUrl::currentMemberId($request);
        $named = ($lastId !== null ? collect($choices)->firstWhere('id', $lastId) : null) ?? (count($choices) === 1 ? $choices[0] : null);
        $workspace = $named === null ? null : ['id' => $named['id'], 'name' => $named['name'], 'url' => $named['url'], 'settings' => ($settings[$named['id']] ?? null) === null ? null : new Action('Workspace settings', $settings[$named['id']], 'get'), 'all' => new Action('All workspaces', $links['switcher'], 'get'), 'leave' => $this->leave($user, (int) $named['id'], $named['name'])];

        $account = self::isAccount($request);

        return new ShellData(['label' => 'Your account', 'url' => '/me', 'current' => $account], $product, $workspace, $choices === [] ? null : ['current' => $current, 'choices' => $choices], $slots['primary'], $identity, $links, app(Registry::class)->webFooter());
    }

    /** The shell of a flow outside a workspace: the product, the account cluster and the page's step. No switcher, navigation, breadcrumbs or footer. */
    private function focus(Request $request, object $user, array $product): FocusShellData
    {
        $avatar = Avatars::urls((int) $user->getKey());

        return new FocusShellData($product, ['name' => (string) $user->name, 'email' => (string) $user->email, 'initials' => $this->initials((string) $user->name), 'avatar_url' => $avatar['url'] ?? null, 'avatar_url_2x' => $avatar['url_2x'] ?? null], ['sign_out' => '/logout', 'switch_account' => '/switch-account'], FocusStep::of($request));
    }

    /** The account menu's one link, from the server: Account settings (the profile; the other personal sections are in the /me sidebar). */
    private function accountItems(object $user, Request $request): array
    {
        $path = '/'.trim($request->path(), '/');

        return [['label' => 'Account settings', 'url' => '/me/profile', 'current' => $path === '/me/profile', 'group' => 'account']];
    }

    /** Leave the named workspace, with a confirmation: only for a member who is not its owner (the owner transfers first), so it is absent, never greyed out. */
    private function leave(object $user, int $workspaceId, string $name): ?Action
    {
        $workspace = MemberWorkspaces::workspace($workspaceId, $user);
        $membership = $workspace === null ? null : RequestMemo::membership($user, $workspace);
        if ($membership === null || app(Concierge::class)->isOwner($membership)) {
            return null;
        }

        return new Action('Leave workspace', '/me/workspaces/'.$workspace->slug.'/membership', 'delete', confirm: ['title' => 'Leave '.$name.'?', 'description' => 'You lose access straight away. An admin can invite you again.', 'label' => 'Leave workspace']);
    }

    /** @param array{suffix: string, active: ?string} $link */
    private function active(Request $request, Workspace $workspace, array $link): bool
    {
        if ((string) $request->route('workspace') !== (string) $workspace->id) {
            return false;
        }
        // The page's path under the workspace: past the `@slug` segment in the full form, as is in the short form.
        $path = $request->decodedPath();
        $path = str_starts_with($path, '@') ? (str_contains($path, '/') ? Str::after($path, '/') : '') : ($path === '/' ? '' : $path);

        if ($link['active'] === '' || ($link['active'] === null && $link['suffix'] === '')) {
            return $path === '';
        }

        return Str::is($link['active'] !== null ? [$link['active']] : [$link['suffix'], $link['suffix'].'/*'], $path);
    }

    public function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(count($parts) > 1 ? mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0] ?? '?', 0, 2));
    }
}
