<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class Audit
{
    /** An event from an MCP connection is recorded as the person via their assistant: `via` names the connection and the client. A `via` the caller supplied is kept as given, never overwritten. */
    public function write(int $actorId, int $workspaceId, string $event, array $details): void
    {
        $principal = request()->attributes->get('mcp.principal');
        if (! array_key_exists('via', $details) && $principal instanceof Principal && $principal->connectionId !== null && $principal->actorId === $actorId) {
            $details['via'] = ['kind' => 'assistant', 'connection_id' => $principal->connectionId, 'client' => request()->attributes->get('mcp.client')];
        }
        $row = ['actor_id' => $actorId, 'workspace_id' => $workspaceId, 'event' => $event, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()];
        // With workspaces (whose migration adds the column), the row keeps the workspace's name, so it still says which workspace it was once deleted.
        if (Schema::hasColumn('audit_events', 'workspace_name')) {
            $row['workspace_name'] = DB::table('workspaces')->where('id', $workspaceId)->value('name');
        }
        DB::table('audit_events')->insert($row);
    }
}
