<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\ClientClock;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\ViewModels\ProfileViewModel;

/** /me/profile: who you are, your photo, and the clock times show in. */
class ProfileController
{
    public function show(Request $request): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $avatar = Avatars::urls((int) $user->getKey());
        $initials = app(PageShell::class)->initials((string) $user->name);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['name' => $user->name, 'avatar' => $avatar]);
        }

        return Inertia::render('Workspace/Account/Profile/Page', new ProfileViewModel('Profile', (string) $user->name, $initials, $avatar, '/me/avatar', (int) config('kit.avatar.max_kb', 2048), (string) $user->email, ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail(), '/user/profile-information', $request->session()->get('account_notice'), '/email/verification-notification', ClientClock::own((int) $user->getKey()) ?? 'workspace', '/me/profile/clock'));
    }
}
