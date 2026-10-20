<?php

namespace Tey\Kit\Features\SessionUi\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

/** Only you change your own avatar. */
final class UserAvatarPolicy
{
    public function update(Authenticatable $user, UserAvatar $avatar): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $avatar->getKey();
    }
}
