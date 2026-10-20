<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The document title of every page, from one source: the page's breadcrumb label (gretel), in the form `{Page} · {Product}`. At depth two
 * or more it names the nearest two crumbs, most specific first (`General · Settings · {Product}`), and it adds the workspace's name only
 * for a user with several workspaces (`Cases · {Workspace} · {Product}`). A page outside the trails (sign-in, an error) names itself
 * through `name()`, once. Shared as the `document_title` prop, and server-rendered in the first response.
 */
final class PageTitle
{
    /** Names a page that has no breadcrumb trail (a guest page, an error): its title for this request. */
    public static function name(string $label, ?Request $request = null): void
    {
        ($request ?? request())->attributes->set('kit.page_title', $label);
    }

    public function forRequest(Request $request): string
    {
        $named = $request->attributes->get('kit.page_title');
        $labels = $this->labels($request);
        // A page that names itself (sign-in, an error) is `{Label} · {Product}` alone; the trails add the workspace.
        $workspace = is_string($named) && $named !== '' ? null : $this->workspaceName($request);

        return implode(' · ', array_filter([...$labels, $workspace, (string) config('app.name')], fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }

    /** @return list<string> the nearest two page labels, most specific first */
    private function labels(Request $request): array
    {
        $named = $request->attributes->get('kit.page_title');
        if (is_string($named) && $named !== '') {
            return [$named];
        }
        $crumbs = $request->route() !== null && $request->route()->getName() !== null ? $request->route()->breadcrumbs()->jsonSerialize() : [];
        // The root crumb is the home icon, named by the workspace or the product: not a page label.
        $pages = array_values(array_map(fn (array $crumb): string => (string) $crumb['title'], array_slice($crumbs, 1)));
        if ($pages === [] && $request->route()?->getName() === 'login') {
            return ['Sign in'];
        }

        return array_slice(array_reverse($pages), 0, 2);
    }

    private function workspaceName(Request $request): ?string
    {
        $user = $request->user('web');
        if ($user === null || count(WorkspaceUrl::memberships($user)) < 2) {
            return null;
        }
        $id = WorkspaceUrl::currentId($request);

        return $id === null ? null : Workspace::query()->whereKey($id)->value('name');
    }
}
