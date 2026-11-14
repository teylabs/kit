<?php

namespace Tey\Kit\Features\SessionUi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Tey\Kit\Features\SessionUi\Account\SignedInDevices;

/**
 * One signed-in session on `signed_in_devices`, the kit's own (infrastructure) table: written and read by SignedInDevices, never by an app's code. A row
 * last seen longer ago than the session lifetime plus `kit.devices.touch_minutes` belongs to a session that has ended, is no longer listed, and
 * `model:prune` deletes it: a host schedules `Schedule::command('model:prune', ['--model' => [SignedInDevice::class]])->daily();`.
 */
class SignedInDevice extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $table = 'signed_in_devices';

    protected $guarded = [];

    /** The session id, even encrypted, and its HMAC never leave the server. */
    protected $hidden = ['session_hash', 'session_id'];

    public function prunable(): Builder
    {
        return static::query()->where('last_seen_at', '<=', SignedInDevices::staleBefore());
    }

    protected function casts(): array
    {
        return ['remembered' => 'boolean', 'created_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
