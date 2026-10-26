<?php

namespace Tey\Kit\Features\Mcp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One stored MCP call result, replayed for a retry with the same intent until `expires_at` (`kit.mcp.intent_ttl` seconds after the call). An expired one counts as gone, and
 * `model:prune` deletes it: a host schedules `Schedule::command('model:prune', ['--model' => [McpIntent::class]])->daily();`.
 */
class McpIntent extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $table = 'mcp_intents';

    protected $guarded = [];

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
