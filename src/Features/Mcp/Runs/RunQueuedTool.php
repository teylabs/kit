<?php

namespace Tey\Kit\Features\Mcp\Runs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Mcp\Events\ToolRunFinished;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Workspaces\Workspaces;
use Throwable;

/**
 * Runs one queued MCP run. Fail closed: it runs only a registered QueuedCapabilityTool, only a run still queued and unexpired, and only while the connection it was queued
 * on is live and still covers the tool's ability; otherwise the run fails with `connection_denied`. It enters the run's workspace itself (Workspaces::run, restored when
 * it returns or throws), not the dispatching request's. A failure is stored as a refusal code, never its message: ConsentGate::refusal() reports an uncoded one.
 * It is not retried: a run is answered once, and a second attempt finds it no longer queued.
 */
final class RunQueuedTool implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $run) {}

    public function handle(Registry $registry, ConsentGate $gate): void
    {
        // Claim the run: only one worker moves it from queued to running.
        $claimed = DB::table('mcp_runs')->where('id', $this->run)->where('status', Runs::QUEUED)->where('expires_at', '>', now())
            ->update(['status' => Runs::RUNNING, 'started_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $run = DB::table('mcp_runs')->where('id', $this->run)->first();
        $principal = new Principal((int) $run->actor_id, (int) $run->connection_id);
        try {
            if (! in_array($run->class, $registry->mcpTools(), true) || ! is_subclass_of($run->class, QueuedCapabilityTool::class)) {
                throw new RuntimeException('capability_unavailable');
            }
            $connection = DB::table('connections')->where('id', $run->connection_id)->where('actor_id', $run->actor_id)->where('workspace_id', $run->workspace_id)
                ->whereNull('revoked_at')->first();
            if ($connection === null || $run->ability === '' || ! WorkspaceConnection::covers((array) json_decode((string) $connection->scopes, true), (string) $run->ability)) {
                throw new RuntimeException('connection_denied');
            }
            $tool = app($run->class);
            $operation = (array) json_decode((string) $run->arguments, true, 512, JSON_THROW_ON_ERROR);
            // The operation authorizes the principal as it does in a call (role, policy, capability, entitlement); the job has no request, so the tool's
            // connection-based unavailable() is not asked: the connection check above stands in for it.
            $result = Workspaces::run((int) $run->workspace_id, function () use ($tool, $principal, $run, $operation): array {
                return $tool->runQueued($this->run, $principal, (int) $run->workspace_id, $operation, $run->intent_key);
            });
            DB::table('mcp_runs')->where('id', $this->run)->update(['status' => Runs::DONE, 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'finished_at' => now()]);
            event(new ToolRunFinished($this->run, (string) $run->tool, $principal, (int) $run->workspace_id, Runs::DONE, null));
        } catch (Throwable $e) {
            $code = $gate->refusal($e)['error'];
            DB::table('mcp_runs')->where('id', $this->run)->update(['status' => Runs::FAILED, 'error' => $code, 'finished_at' => now()]);
            event(new ToolRunFinished($this->run, (string) $run->tool, $principal, (int) $run->workspace_id, Runs::FAILED, $code));
        }
    }
}
