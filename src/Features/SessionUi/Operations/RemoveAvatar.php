<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

/** Removes a user's avatar (the file and its thumbnails); the UI falls back to their initials. */
class RemoveAvatar
{
    use AsAction;

    public function handle(UserAvatar $holder): void
    {
        $holder->clearMediaCollection('avatar');
    }
}
