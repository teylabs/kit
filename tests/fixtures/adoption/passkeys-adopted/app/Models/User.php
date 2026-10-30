<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;

final class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable;
}
