<?php

namespace Tey\Kit\Features\SessionUi;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Data\FocusShellData;
use Tey\Kit\Features\SessionUi\Data\GuestShellData;
use Tey\Kit\Features\SessionUi\Search\Searches;
use Tey\Kit\Features\Shell\Data\ShellData;
use Tey\Kit\Features\Shell\ShellSource;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

final class PageShell implements ShellSource
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
        if (! app(Registry::class)->adopts('workspaces')) {
            return null;
        }
        $shell = app(self::class);
        $lastId = WorkspaceUrl::currentMemberId($request);
        $last = $lastId === null ? null : collect($shell->choices($request))->firstWhere('id', $lastId);

        return $last === null || $last['url'] === null ? null : new NavigationHeader('Back to '.$last['name'], $last['url']);
    }

    public function forRequest(Request $request): ShellData|GuestShellData|FocusShellData
    {
        $user = $request->user('web');
        $links = ['home' => Landing::to('/me/workspaces'), 'switcher' => Landing::to('/me/workspaces'), 'sign_in' => '/login', 'sign_out' => null];
        // The platform's identity: APP_NAME and its logomark. The workspace is separate (`switcher.current`) and never the mark.
        $product = ['name' => (string) config('app.name'), 'url' => Landing::to('/me/workspaces'), 'logomark' => config('kit.brand.logomark') ?: null, 'logomark_dark' => config('kit.brand.logomark_dark') ?: null, 'wordmark' => config('kit.brand.wordmark') ?: null, 'wordmark_dark' => config('kit.brand.wordmark_dark') ?: null, 'lockup' => (bool) config('kit.brand.lockup')];
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
        // Without workspaces (session-ui on a core and shell host) there is no workspace to name, switch or navigate: the bar is the product and the account.
        if (app(Registry::class)->adopts('workspaces') && RequestMemo::account($user)->isActive() && $user->conciergeHasVerifiedEmail()) {
            // Only the viewer's own active memberships, in one query: never every workspace.
            foreach (MemberWorkspaces::workspaces($user) as $workspace) {
                $allowed = app(TenantBoundary::class)->run((int) $workspace->id, function () use ($workspace, $user, $request): array {
                    $allowed = ['primary' => [], 'user-menu' => [], 'settings' => null, 'module' => null, 'search' => null];
                    $primary = [];
                    foreach (app(Registry::class)->navigationLinks() as $link) {
                        if ($this->may($user, $link['ability'])) {
                            $url = WorkspaceUrl::to($workspace, $link['suffix'], null, $request);
                            $allowed[$link['slot']][] = ['label' => $link['label'], 'url' => $url, 'current' => $this->active($request, $workspace, $link), 'group' => $link['group']];
                            if ($link['slot'] === 'primary') {
                                $primary[] = ['label' => $link['label'], 'suffix' => $link['suffix'], 'current' => end($allowed['primary'])['current']];
                            }
                        }
                    }
                    // The SubNav, for the workspace being viewed only: the active module's pages the viewer may open.
                    if ((string) $request->route('workspace') === (string) $workspace->id) {
                        $allowed['module'] = self::moduleNavigation(app(Registry::class), $primary, fn (string $ability): bool => $this->may($user, $ability), fn (array $page): string => WorkspaceUrl::to($workspace, $page['suffix'], null, $request), fn (array $page): bool => $this->active($request, $workspace, $page));
                    }

                    // Global search, for the workspace being viewed only, while the viewer may search it (workspace.search on).
                    if ((string) $request->route('workspace') === (string) $workspace->id && $this->may($user, Searches::ABILITY)) {
                        $allowed['search'] = ['url' => WorkspaceUrl::to($workspace, 'search', 'session-ui', $request), 'label' => 'Search', 'placeholder' => 'Search '.$workspace->name];
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
        $lastId = $current !== null ? $current['id'] : ($choices === [] ? null : WorkspaceUrl::currentMemberId($request));
        $named = ($lastId !== null ? collect($choices)->firstWhere('id', $lastId) : null) ?? (count($choices) === 1 ? $choices[0] : null);
        $workspace = $named === null ? null : ['id' => $named['id'], 'name' => $named['name'], 'url' => $named['url'], 'settings' => ($settings[$named['id']] ?? null) === null ? null : new Action('Workspace settings', $settings[$named['id']], 'get'), 'all' => new Action('All workspaces', $links['switcher'], 'get'), 'leave' => $this->leave($user, (int) $named['id'], $named['name'])];

        $account = self::isAccount($request);

        return new ShellData(['label' => 'Your account', 'url' => Landing::to('/me'), 'current' => $account], $product, $workspace, $choices === [] ? null : ['current' => $current, 'choices' => $choices], $slots['primary'], $identity, $links, app(Registry::class)->webFooter(), subNavigation: $slots['module'] ?? null, search: $slots['search'] ?? null);
    }

    /**
     * The SubNav of the active module: the allowed primary link that is current, or whose own page is current (Registry::modulePage()).
     * Its pages the viewer may open, in order; when several match the path, only the most specific (longest suffix) is current, and none
     * is made current when none matches. Null without an active module, or when the viewer may open none of its pages.
     *
     * @param  list<array{label: string, suffix: string, current: bool}>  $primary  the allowed primary links, in order
     * @param  Closure(string): bool  $may  whether the viewer holds an ability
     * @param  Closure(array): string  $url  a page's URL
     * @param  Closure(array): bool  $current  whether a page's `active` pattern matches the path
     * @return ?array{label: string, items: list<array{label: string, url: string, current: bool}>}
     */
    public static function moduleNavigation(Registry $registry, array $primary, Closure $may, Closure $url, Closure $current): ?array
    {
        foreach ($primary as $link) {
            $pages = $registry->modulePages($link['suffix']);
            $matching = array_filter($pages, $current);
            if (! $link['current'] && $matching === []) {
                continue;
            }
            $specific = $matching === [] ? null : array_reduce(array_keys($matching), fn (?int $best, int $index): int => $best === null || strlen($pages[$index]['suffix']) > strlen($pages[$best]['suffix']) ? $index : $best);
            $items = [];
            foreach ($pages as $index => $page) {
                if ($may($page['ability'])) {
                    $items[] = ['label' => $page['label'], 'url' => $url($page), 'current' => $index === $specific];
                }
            }

            return $items === [] ? null : ['label' => $link['label'], 'items' => $items];
        }

        return null;
    }

    /** Whether the viewer holds the ability here; the refusals a navigation link can meet hide it, anything else is an error. */
    private function may(object $user, string $ability): bool
    {
        try {
            app(Access::class)->authorize(new Principal((int) $user->id), $ability);

            return true;
        } catch (RuntimeException $exception) {
            if (! in_array($exception->getMessage(), ['workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'capability_unavailable', 'entitlement_unavailable', 'entitlement_required'], true)) {
                throw $exception;
            }

            return false;
        }
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

        // Without workspace-ui (auth-only) there are no account pages to link to.
        return app(Registry::class)->adopts('workspace-ui') ? [['label' => 'Account settings', 'url' => '/me/profile', 'current' => $path === '/me/profile', 'group' => 'account']] : [];
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

    /** @param array{suffix: string, active: string|list<string>|null} $link */
    private function active(Request $request, Workspace $workspace, array $link): bool
    {
        if ((string) $request->route('workspace') !== (string) $workspace->id) {
            return false;
        }
        // The page's path under the workspace: past the `@slug` segment in the full form, as is in the short form.
        $path = $request->decodedPath();
        $path = str_starts_with($path, '@') ? (str_contains($path, '/') ? Str::after($path, '/') : '') : ($path === '/' ? '' : $path);

        return self::matches($link, $path, $request->route()?->getName());
    }

    /**
     * Whether a link is current on a path under the workspace and a route name. `active` is one pattern, or a list of them (current when any matches); an entry
     * `route:<pattern>` matches the route's name, any other entry the path (`''` the workspace root only). With no `active` it is the suffix and everything below it.
     *
     * @param  array{suffix: string, active: string|list<string>|null}  $link
     */
    public static function matches(array $link, string $path, ?string $routeName = null): bool
    {
        if ($link['active'] === null && $link['suffix'] === '') {
            return $path === '';
        }
        $entries = $link['active'] === null ? [$link['suffix'], $link['suffix'].'/*'] : (array) $link['active'];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, 'route:')) {
                if ($routeName !== null && Str::is(substr($entry, 6), $routeName)) {
                    return true;
                }
            } elseif ($entry === '' ? $path === '' : Str::is($entry, $path)) {
                return true;
            }
        }

        return false;
    }

    public function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(count($parts) > 1 ? mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0] ?? '?', 0, 2));
    }
}
