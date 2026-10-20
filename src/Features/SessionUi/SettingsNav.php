<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The sidebar of a settings section's page: every section the user may open, grouped like the landing page (same server data), with the one
 * being shown marked `current`. Null off the section pages, and on the landing pages (which are the card grids).
 */
final class SettingsNav
{
    /** @return ?array{title: string, groups: list<array{label: string, sections: list<array{key: string, label: string, description: string, icon: string, url: string, current: bool}>}>} */
    public function forRequest(Request $request): ?array
    {
        $user = $request->user('web');
        if (! Users::is($user)) {
            return null;
        }
        $path = '/'.trim($request->path(), '/');
        $principal = new Principal((int) $user->getKey());
        $workspaceId = WorkspaceUrl::currentId($request);
        $workspace = $workspaceId === null ? null : Workspace::query()->find($workspaceId);
        // Personal sections are `/me/{key}`; workspace ones are `/@{slug}/settings/{key}` or `/settings/{key}`.
        $personal = str_starts_with($path, '/me/');
        if (! $personal && ($workspace === null || ! preg_match('#^(?:/@[^/]+)?/settings/[^/]+#', $path))) {
            return null;
        }
        // What may be opened is decided in the workspace's context (restored afterwards), as on the landing page.
        $sections = $personal ? SettingsSections::visible('personal', null, $principal, $request) : app(TenantBoundary::class)->run((int) $workspace->getKey(), fn (): array => SettingsSections::visible('workspace', $workspace, $principal, $request));
        $groups = SettingsSections::grouped(array_map(fn (array $section): array => $section + ['current' => $this->is($section['url'], $path)], $sections));

        return ['title' => $personal ? 'Account' : 'Settings', 'groups' => $groups];
    }

    private function is(string $url, string $path): bool
    {
        $target = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');
        // The section's own page, however its URL is written (short or `@`): compare the part after the workspace marker.
        $strip = fn (string $value): string => preg_replace('#^/@[^/]+#', '', $value) ?: '/';

        return $strip($target) === $strip($path) || str_starts_with($strip($path), $strip($target).'/');
    }
}
