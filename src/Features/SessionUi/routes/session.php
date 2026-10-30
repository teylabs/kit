<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Tey\Kit\Features\Core\PersonalPages;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Workbench\WorkbenchKey;
use Tey\Kit\Features\SessionUi\Http\AvatarController;
use Tey\Kit\Features\SessionUi\Http\AvatarImageController;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\Middleware\PrepareKitRequest;
use Tey\Kit\Features\SessionUi\Http\Middleware\StartKitRequest;
use Tey\Kit\Features\SessionUi\Http\PasskeyOfferController;
use Tey\Kit\Features\SessionUi\Http\SwitchAccountController;
use Tey\Kit\Features\SessionUi\Http\WelcomeController;
use Tey\Kit\Features\SessionUi\Http\WelcomeSkipController;
use Tey\Kit\Features\SessionUi\Http\WorkbenchPreferenceController;

// The sign-in side: Fortify serves the auth pages themselves (SessionUiProvider names their views). The workspace and account pages, the invitation
// link and the first-run demo's sign-in are workspace-ui's (its routes/workspace.php). Their trails root at the kit's home, which is workspace-ui's too:
// without it these pages are their own roots. Nothing here needs a workspace or Concierge: on a core and shell host it is the sign-in alone.
$home = app(Registry::class)->adopts('workspace-ui') ? ['session-ui', 'home'] : null;
// Concierge's active-account check, where workspaces brought Concierge.
Route::middleware(app(Registry::class)->adopts('workspaces') ? ['auth:web', 'concierge.active'] : ['auth:web'])->group(function (): void {
    // Your Workbench layouts (pane sizes, collapsed panes, zoom, the phone tab), per workbench key: JSON, with a stale-write guard.
    Route::controller(WorkbenchPreferenceController::class)->prefix('me/workbenches')->middleware('throttle:120,1')->group(function (): void {
        Route::put('{key}', 'update')->where('key', WorkbenchKey::PATTERN)->name('me.workbenches.update');
    });
    // Your own avatar: no id in the URL, it is always the signed-in user's.
    Route::controller(AvatarController::class)->prefix('me/avatar')->group(function (): void {
        Route::post('', 'store')->name('me.avatar.store');
        Route::delete('', 'destroy')->name('me.avatar.destroy');
    });
});
// An avatar's image: public, by its random token, with no session or cookie so it can be cached by anything.
Route::controller(AvatarImageController::class)->prefix('avatars')->group(function (): void {
    Route::get('{token}/{size?}', 'show')->where(['token' => '[A-Za-z0-9]{40}', 'size' => '2x'])->name('avatars.show')
        ->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, HandleInertiaRequests::class, StartKitRequest::class, PrepareKitRequest::class]);
});
// Switch account: signs this session out and returns to sign in (the FocusShell's account cluster).
Route::post('switch-account', SwitchAccountController::class)->name('account.switch')->middleware('auth:web');
// The post-sign-up passkey offer's "Not now" (PasskeyOffer).
Route::controller(PasskeyOfferController::class)->middleware('auth:web')->group(function (): void {
    Route::delete('me/passkey-offer', 'destroy')->name('me.passkey-offer.dismiss');
});
// The welcome steps after joining (FocusShell): the next pending step, one step, saving it, skipping it. None blocks.
Route::controller(WelcomeController::class)->middleware('auth:web')->prefix('welcome')->group(function () use ($home): void {
    PersonalPages::page(Route::get('', 'index')->name('focus.welcome'), 'Welcome', $home);
    PersonalPages::page(Route::get('{step}', 'show')->where('step', '[a-z0-9-]+')->name('focus.welcome.show'), 'Welcome', $home);
    Route::put('{step}', 'update')->where('step', '[a-z0-9-]+')->name('focus.welcome.update');
    Route::post('{step}', 'store')->where('step', '[a-z0-9-]+')->name('focus.welcome.store');
});
Route::post('welcome/{step}/skip', WelcomeSkipController::class)->where('step', '[a-z0-9-]+')->middleware('auth:web')->name('focus.welcome.skip');
