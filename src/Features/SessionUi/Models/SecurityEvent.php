<?php

namespace Tey\Kit\Features\SessionUi\Models;

use Illuminate\Database\Eloquent\Model;

/** One entry of a user's own security history. It belongs to the user, never to a workspace, and is kept. */
class SecurityEvent extends Model
{
    public $timestamps = false;

    protected $table = 'kit_security_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
