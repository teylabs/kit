<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Workspaces\Http\InvitationsController;
use Tey\Kit\Features\Workspaces\Http\MembershipController;
use Tey\Kit\Features\Workspaces\Http\MovedInvitationController;
use Tey\Kit\Features\Workspaces\Http\MovedWorkspaceListController;
use Tey\Kit\Features\Workspaces\Http\MovedWorkspacePageController;
use Tey\Kit\Features\Workspaces\Http\WorkspaceController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    // The chooser and the invitations are personal, cross-workspace pages: they live under `/me`.
    // Auth-only (session-ui without workspace-ui) there is no chooser page and no /me to trail to: the address, and its name, send the person to the app's own home.
    if (WorkspaceUrl::authOnly()) {
        Route::get('me/workspaces', static fn () => redirect(WorkspaceUrl::chooser()))->name('workspaces.index');
    } else {
        Route::controller(WorkspaceController::class)->prefix('me/workspaces')->group(function (): void {
            WorkspaceRoutes::personalPage(Route::get('', 'index')->name('workspaces.index'), 'Workspaces', ['session-ui', 'me.index']);
        });
    }
    // Leaving a workspace: ending your own membership of it.
    Route::controller(MembershipController::class)->prefix('me/workspaces/{workspace}/membership')->group(function (): void {
        Route::delete('', 'destroy')->where('workspace', '[a-z0-9]+(?:-[a-z0-9]+)*');
    });
    Route::controller(InvitationsController::class)->prefix('me/invitations')->group(function (): void {
        Route::post('', 'store');
    });
    // The old URLs are permanently moved: `/workspaces` to `/me/workspaces`, `/workspaces/{id or slug}/...` to `/@{slug}/...`, and the accept URL to `/me/invitations`.
    Route::get('workspaces', MovedWorkspaceListController::class);
    Route::get('workspaces/{workspace}/{path?}', MovedWorkspacePageController::class)->where('path', '.*');
    Route::match(['GET', 'POST'], 'tenant-invitations/accept', MovedInvitationController::class);
});
