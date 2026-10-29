<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use RuntimeException;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** What the settings landing pages list: the registered sections a user may open, grouped, in the registry's order. */
final class SettingsSections
{
    /**
     * @param  ?Workspace  $workspace  the workspace whose context the caller has entered, or null for the personal scope
     * @return list<array{key: string, label: string, description: string, group: string, icon: string, url: string}>
     */
    public static function visible(string $scope, ?Workspace $workspace, Principal $principal, Request $request): array
    {
        $visible = [];
        foreach (app(Registry::class)->settingsSections($scope) as $section) {
            // The danger zone's deletions are listed only when the host has turned them on (Deletions).
            if ($section['key'] === 'delete' && ! Deletions::enabled($scope === 'personal' ? Deletions::ACCOUNT : Deletions::WORKSPACE)) {
                continue;
            }
            if ($section['ability'] !== null && ! self::may($section['ability'], $principal)) {
                continue;
            }
            $visible[] = ['key' => $section['key'], 'label' => $section['label'], 'description' => $section['description'], 'group' => $section['group'], 'icon' => $section['icon'] ?? 'settings', 'badge' => ($section['placeholder'] ?? false) ? 'Planned' : null, 'url' => $scope === 'personal' || $workspace === null ? '/me/'.$section['key'] : WorkspaceUrl::to($workspace, 'settings/'.$section['key'], null, $request)];
        }

        return $visible;
    }

    /**
     * @param  list<array{key: string, label: string, description: string, group: string, icon: string, url: string}>  $sections
     * @return list<array{label: string, sections: list<array{key: string, label: string, description: string, icon: string, url: string}>}> groups in the order they first appear
     */
    public static function grouped(array $sections): array
    {
        $groups = [];
        foreach ($sections as $section) {
            $groups[$section['group']][] = ['key' => $section['key'], 'label' => $section['label'], 'description' => $section['description'], 'icon' => $section['icon'], 'badge' => $section['badge'] ?? null, 'url' => $section['url']] + (isset($section['current']) ? ['current' => $section['current']] : []);
        }

        return array_map(fn (string $label, array $items): array => ['label' => $label, 'sections' => $items], array_keys($groups), array_values($groups));
    }

    private static function may(string $ability, Principal $principal): bool
    {
        try {
            app(Access::class)->authorize($principal, $ability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
