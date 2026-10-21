<?php

namespace Tey\Kit\Features\Settings\Http;

use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Data\SettingsView;
use Tey\Kit\Features\Settings\ViewModels\EditSettingsViewModel;
use Tey\Kit\Features\Workspaces\Models\Workspace;
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
        // The page's `can` flag is the policy's answer for this workspace, never a role name.
        $canWrite = app(TenantBoundary::class)->run($workspace, fn (): bool => Gate::forUser($request->user())->allows('update', Workspace::required()));

        [$isOwner, $heirs, $leaveUrl] = app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace): array {
            $concierge = app(Concierge::class);
            $tenant = Workspace::required();
            $mine = $concierge->membership($request->user(), $tenant);
            $owner = $mine !== null && $concierge->isOwner($mine);
            $heirs = $owner ? ConciergeModels::membership()::query()->with('user')->where('workspace_id', $workspace)->where('status', MembershipStatus::Active->value)->whereKeyNot($mine->getKey())->orderBy('id')->get()
                ->filter(fn ($membership): bool => $membership->user->conciergeHasVerifiedEmail())
                ->map(fn ($membership): array => ['value' => (string) $membership->getKey(), 'label' => $membership->user->name.' ('.$membership->user->email.')'])->values()->all() : [];

            return [$owner, $heirs, $mine !== null && ! $owner ? '/me/workspaces/'.$tenant->slug.'/membership' : null];
        });

        return Inertia::render('Workspace/Settings/General/Page', new EditSettingsViewModel('Workspace settings', SettingsView::from($settings), $canWrite, WorkspaceUrl::to($workspace, 'settings/general', 'settings'), (string) Str::uuid(), $isOwner, $heirs, WorkspaceUrl::to($workspace, 'settings/ownership', 'session-ui'), $leaveUrl));
    }

    public function update(Request $request, int $workspace): mixed
    {
        $settings = app(SettingsUi::class)->update($this->principal($request), $workspace, $request->except(['_token', '_method']));

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($settings) : redirect(WorkspaceUrl::to($workspace, 'settings/general', 'settings'))->with('status', 'Workspace settings saved.');
    }
}
