<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\SettingsSections;
use Tey\Kit\Features\SessionUi\ViewModels\SectionsViewModel;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

/** The two settings landing pages: a workspace's, and the user's own (`/me`). Each lists the sections registered for it. */
class SectionsController
{
    public function workspace(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request): mixed {
            return $this->page($request, 'Settings', SettingsSections::visible('workspace', Workspace::required(), $this->principal($request), $request));
        });
    }

    public function personal(Request $request): mixed
    {
        return $this->page($request, 'Account', SettingsSections::visible('personal', null, $this->principal($request), $request));
    }

    /** @param list<array{key: string, label: string, description: string, group: string, url: string}> $sections */
    private function page(Request $request, string $title, array $sections): mixed
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['sections' => array_map(fn (array $section): array => ['key' => $section['key'], 'label' => $section['label'], 'description' => $section['description'], 'group' => $section['group'], 'url' => $section['url']], $sections)]);
        }

        return Inertia::render('Workspace/Sections/Index/Page', new SectionsViewModel($title, SettingsSections::grouped($sections)));
    }

    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }
}
