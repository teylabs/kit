<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;

final class User extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $fillable = ['name', 'email'];
}
