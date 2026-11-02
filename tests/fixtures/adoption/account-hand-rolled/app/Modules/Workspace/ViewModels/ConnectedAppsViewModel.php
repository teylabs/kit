<?php

namespace App\Modules\Workspace\ViewModels;

use Laravel\Passport\Token;

final class ConnectedAppsViewModel
{
    public function count(int $user): int
    {
        return Token::query()->where('user_id', $user)->count();
    }
}
