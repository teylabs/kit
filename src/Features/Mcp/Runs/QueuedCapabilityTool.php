<?php

namespace Tey\Kit\Features\Mcp\Runs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

/**
 * A capability tool for an operation too slow to answer in the call (a report, an import). Admission is CapabilityTool's, unchanged: consent, arguments, confirmation,
 * intent replay. Then, instead of running `call()`, it stores a run (`kit_mcp_runs`), queues `RunQueuedTool` and answers at once with
 * `{status: "queued", run, poll_after_seconds, expires_at}`. The assistant reads the run back with `runs_read`. A retry with the same `intent_key` replays that answer, so
 * it gets the same run, not a second job. `call()` keeps its signature and runs in the job, in the run's workspace; it may report its step with `progress()`.
 */
abstract class QueuedCapabilityTool extends CapabilityTool
{
    /** The run `call()` is running in, while the job runs it; progress() writes to it. */
    private ?string $runningRun = null;

    protected function admitted(Principal $principal, int $workspace, array $operation, array $validated): array
    {
        if ($principal->connectionId === null) {
            throw new \RuntimeException('connection_denied');
        }
        $ability = Capability::of($this)?->scope ?? (defined(static::class.'::ABILITY') ? static::ABILITY : null);
        $id = 'run_'.strtolower((string) Str::ulid());
        $expires = now()->addSeconds(Runs::ttl());
        // An expired run is gone: its rows go when the same connection queues the next one.
        DB::table('kit_mcp_runs')->where('actor_id', $principal->actorId)->where('connection_id', $principal->connectionId)->where('expires_at', '<=', now())->delete();
        DB::table('kit_mcp_runs')->insert([
            'id' => $id, 'tool' => $this->name(), 'class' => static::class, 'ability' => (string) $ability, 'actor_id' => $principal->actorId,
            'connection_id' => $principal->connectionId, 'workspace_id' => $workspace, 'arguments' => json_encode($operation, JSON_THROW_ON_ERROR),
            'intent_key' => isset($validated['intent_key']) ? (string) $validated['intent_key'] : null, 'status' => Runs::QUEUED, 'created_at' => now(), 'expires_at' => $expires,
        ]);
        // Dispatched after the transaction commits, so the job never looks for a run that is not there yet (or was rolled back).
        RunQueuedTool::dispatch($id)->afterCommit();

        return ['status' => Runs::QUEUED, 'run' => $id, 'poll_after_seconds' => Runs::pollAfter(), 'expires_at' => $expires->toIso8601String()];
    }

    /**
     * Where the run is, for the assistant: a short step name in the tool's own words ("Rendering PDF") and, when known, how many of how many are done. Shown as is,
     * so never an exception message. Ignored outside a queued run and once the run has finished.
     */
    protected function progress(string $step, ?int $done = null, ?int $total = null): void
    {
        if ($this->runningRun === null) {
            return;
        }
        DB::table('kit_mcp_runs')->where('id', $this->runningRun)->where('status', Runs::RUNNING)
            ->update(['step' => Str::limit($step, 117), 'done' => $done === null ? null : max(0, $done), 'total' => $total === null ? null : max(0, $total)]);
    }

    /**
     * The job's way in: runs `call()` for a stored run, with progress() writing to it. The job has already re-admitted the run and entered its workspace.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     *
     * @internal
     */
    final public function runQueued(string $run, Principal $principal, int $workspace, array $operation, ?string $intent): array
    {
        $this->runningRun = $run;
        try {
            return $this->perform($principal, $workspace, $operation, $intent);
        } finally {
            $this->runningRun = null;
        }
    }
}
