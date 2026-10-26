<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Support\Facades\DB;

final class Audit
{
    /** An event from an MCP connection is recorded as the person via their assistant: `via` names the connection and the client. A `via` the caller supplied is kept as given, never overwritten. */
    public function write(int $actorId, int $workspaceId, string $event, array $details): void
    {
        $principal = request()->attributes->get('mcp.principal');
        if (! array_key_exists('via', $details) && $principal instanceof Principal && $principal->connectionId !== null && $principal->actorId === $actorId) {
            $details['via'] = ['kind' => 'assistant', 'connection_id' => $principal->connectionId, 'client' => request()->attributes->get('mcp.client')];
        }
        DB::table('audit_events')->insert(['actor_id' => $actorId, 'workspace_id' => $workspaceId, 'event' => $event, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
