<?php

namespace Tey\Kit\Features\Settings\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Data\SettingsView;
use Tey\Kit\Features\Settings\ViewModels\EditSettingsViewModel;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

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
        // The page's `can` flag is the policy's answer for this workspace, never a role name.
        $canWrite = app(TenantBoundary::class)->run($workspace, fn (): bool => Gate::forUser($request->user())->allows('update', Workspace::required()));

        // Save is absent for a viewer whose role can never change the settings (the page is read-only); it is only ever disabled while a usable save is temporarily blocked.
        $save = $canWrite ? new Action('Save', WorkspaceUrl::to($workspace, 'settings/general', 'settings'), 'put') : null;

        return Inertia::render('Workspace/Settings/General/Page', new EditSettingsViewModel('Workspace settings', SettingsView::from($settings), $save, (string) Str::uuid()));
    }

    public function update(Request $request, int $workspace): mixed
    {
        $settings = app(SettingsUi::class)->update($this->principal($request), $workspace, $request->except(['_token', '_method']));

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($settings);
        }
        // A save is confirmed with a toast that pulses the Save button (docs/toasts.md).
        Toast::success('Workspace settings saved.')->from('save');

        return redirect(WorkspaceUrl::to($workspace, 'settings/general', 'settings'));
    }
}
