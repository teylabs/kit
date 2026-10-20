<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Data\BreadcrumbData;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The trail to the page being shown, built from what features register rather than declared per page: the nav links, the
 * pages with a parent (`Registry::page`) and the record pages (`Registry::record`). A page only names itself; a record
 * page's title comes from its model. Pages under `me/` are personal and belong to no workspace.
 */
final class Breadcrumbs
{
    /**
     * @param  ?Workspace  $workspace  the current workspace, whose context the caller has already entered (for abilities and record lookups)
     * @return list<BreadcrumbData>
     */
    public function trail(Request $request, ?Workspace $workspace, Principal $principal): array
    {
        $nodes = $this->nodes();
        $path = $this->path($request, $workspace);
        $leaf = $this->leaf($nodes, $path, $workspace);
        if ($leaf === null) {
            return [];
        }
        $chain = [$leaf];
        for ($node = $leaf, $guard = 0; $node['parent'] !== null && isset($nodes[$node['parent']]) && $guard < 8; $guard++) {
            $chain[] = $node = $nodes[$node['parent']] + ['id' => null];
        }
        $chain = array_reverse($chain);
        $trail = [];
        foreach ($chain as $index => $node) {
            $suffix = $node['id'] === null ? $node['suffix'] : $node['suffix'].'/'.$node['id'];
            $linked = $this->may($node['ability'], $workspace, $principal);
            $trail[] = new BreadcrumbData($node['label'], $linked ? $this->url($request, $workspace, $suffix) : null, $index === count($chain) - 1);
        }

        return $trail;
    }

    /** @return array<string, array{label: string, suffix: string, ability: ?string, parent: ?string, id: null}> */
    private function nodes(): array
    {
        $registry = app(Registry::class);
        $nodes = [];
        foreach ($registry->navigationLinks() as $link) {
            $nodes[$link['suffix']] = ['label' => $link['label'], 'suffix' => $link['suffix'], 'ability' => $link['ability'], 'parent' => null, 'id' => null];
        }
        foreach ($registry->pages() as $page) {
            $nodes[$page['suffix']] = ['label' => $page['label'], 'suffix' => $page['suffix'], 'ability' => $page['ability'], 'parent' => $page['parent'], 'id' => null];
        }

        return $nodes;
    }

    /** The page's path: under the workspace for a workspace page (past its `@slug`), as is for a personal one. */
    private function path(Request $request, ?Workspace $workspace): string
    {
        $path = $request->decodedPath();

        return $workspace !== null && str_starts_with($path, '@') ? (string) str($path)->after('/') : $path;
    }

    /** @return ?array{label: string, suffix: string, ability: ?string, parent: ?string, id: ?string} */
    private function leaf(array $nodes, string $path, ?Workspace $workspace): ?array
    {
        $personal = $path === 'me' || str_starts_with($path, 'me/');
        if (! $personal && $workspace === null) {
            return null;
        }
        if (! $personal) {
            foreach (app(Registry::class)->records() as $record) {
                if (preg_match('#\A'.preg_quote($record['parent'], '#').'/(\d+)\z#', $path, $matches) === 1 && isset($nodes[$record['parent']])) {
                    $model = $record['model']::query()->find((int) $matches[1]);

                    return $model === null ? null : ['label' => $model->breadcrumbTitle(), 'suffix' => $record['parent'], 'ability' => $record['ability'], 'parent' => $record['parent'], 'id' => $matches[1]];
                }
            }
        }
        $best = null;
        foreach ($nodes as $suffix => $node) {
            $isPersonal = $suffix === 'me' || str_starts_with($suffix, 'me/');
            if ($isPersonal === $personal && ($path === $suffix || str_starts_with($path, $suffix.'/')) && ($best === null || strlen($suffix) > strlen($best['suffix']))) {
                $best = $node + ['id' => null];
            }
        }

        return $best;
    }

    private function may(?string $ability, ?Workspace $workspace, Principal $principal): bool
    {
        if ($ability === null) {
            return true;
        }
        if ($workspace === null) {
            return false;
        }
        try {
            app(Access::class)->authorize($principal, $ability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function url(Request $request, ?Workspace $workspace, string $suffix): string
    {
        return $workspace === null || str_starts_with($suffix, 'me/') ? '/'.$suffix : WorkspaceUrl::to($workspace, $suffix, null, $request);
    }
}
