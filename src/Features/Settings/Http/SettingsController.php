<?php

namespace Tey\Kit\Features\Settings\Http;

use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Data\SettingsView;
use Tey\Kit\Features\Settings\ViewModels\EditSettingsViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;

class SettingsController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function show(Request $request, int $workspace): mixed
    {
        $settings = app(SettingsUi::class)->show($this->principal($request), $workspace);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($settings);
        }
        $canWrite = app(TenantBoundary::class)->run($workspace, function () use ($request): bool {
            try {
                app(Access::class)->authorize($this->principal($request), 'settings.write');

                return true;
            } catch (\RuntimeException $exception) {
                if ($exception->getMessage() !== 'permission_denied') {
                    throw $exception;
                }

                return false;
            }
        });

        return Inertia::render('Workspace/Settings/Edit/Page', new EditSettingsViewModel('Workspace settings', SettingsView::from($settings), $canWrite, WorkspaceUrl::to($workspace, 'settings', 'settings'), (string) Str::uuid()));
    }

    public function update(Request $request, int $workspace): mixed
    {
        $settings = app(SettingsUi::class)->update($this->principal($request), $workspace, $request->except(['_token', '_method']));

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($settings) : redirect(WorkspaceUrl::to($workspace, 'settings', 'settings'))->with('status', 'Workspace settings saved.');
    }
}
