<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Data\NavGroup;
use Tey\Kit\Features\Core\Data\NavItem;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The section navigation of a settings section's page: every section the user may open, grouped like the landing page (the same server data), with the one
 * being shown `current` (decided here, covering its descendants). A page's layout carries it as `layout.navigation`.
 */
final class SettingsNav
{
    /**
     * @param  string  $scope  `workspace` (the current workspace's sections) or `personal` (the account's)
     * @return list<NavGroup>
     */
    public function groups(Request $request, string $scope): array
    {
        $user = $request->user('web');
        if (! Users::is($user)) {
            return [];
        }
        $path = '/'.trim($request->path(), '/');
        $principal = new Principal((int) $user->getKey());
        if ($scope === 'personal') {
            $sections = SettingsSections::visible('personal', null, $principal, $request);
        } else {
            $workspaceId = WorkspaceUrl::currentMemberId($request);
            $workspace = $workspaceId === null ? null : Workspaces::query()->find($workspaceId);
            if ($workspace === null) {
                return [];
            }
            // What may be opened is decided in the workspace's context (restored afterwards), as on the landing page.
            $sections = app(TenantBoundary::class)->run((int) $workspace->getKey(), fn (): array => SettingsSections::visible('workspace', $workspace, $principal, $request));
        }

        return array_map(
            fn (array $group): NavGroup => new NavGroup($group['label'], array_map(fn (array $section): NavItem => new NavItem($section['label'], $section['url'], $this->is($section['url'], $path), $section['icon'], $section['badge'] ?? null), $group['sections'])),
            SettingsSections::grouped($sections),
        );
    }

    private function is(string $url, string $path): bool
    {
        $target = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');
        // The section's own page, however its URL is written (short or `@`): compare the part after the workspace marker.
        $strip = fn (string $value): string => preg_replace('#^/@[^/]+#', '', $value) ?: '/';

        return $strip($target) === $strip($path) || str_starts_with($strip($path), $strip($target).'/');
    }
}
