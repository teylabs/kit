<?php

namespace Tey\Kit\Features\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/** One workspace's value of a registered workspace setting. `value` is the setting's JSON, encrypted with the app key; read it through SettingValues. */
final class WorkspaceSecret extends Model
{
    protected $table = 'workspace_secrets';

    protected $guarded = [];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'workspace_id' => 'integer'];
    }
}
