<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;
use Tey\Kit\Features\SessionUi\Operations\RemoveAvatar;
use Tey\Kit\Features\SessionUi\Operations\StoreAvatar;

/** Your own avatar: set (or replace) and remove. There is no id in the URL: it is always the signed-in user's. */
class AvatarController
{
    public function store(Request $request): mixed
    {
        $holder = $this->holder($request);
        StoreAvatar::run($holder, $request->file('avatar') ?? abort(422, 'Choose a photo.'));

        return $request->expectsJson() ? response()->json(['avatar' => Avatars::urls((int) $holder->getKey())], 201) : back();
    }

    public function destroy(Request $request): mixed
    {
        RemoveAvatar::run($this->holder($request));

        return $request->expectsJson() ? response()->json(['avatar' => null]) : back();
    }

    private function holder(Request $request): UserAvatar
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $holder = UserAvatar::query()->findOrFail($user->getKey());
        Gate::forUser($user)->authorize('update', $holder);

        return $holder;
    }
}
