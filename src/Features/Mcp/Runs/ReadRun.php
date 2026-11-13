<?php

namespace Tey\Kit\Features\Mcp\Runs;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;
use Tey\Kit\Features\Core\Mcp\ServedBeside;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Workspaces\Workspaces;
use Throwable;

/**
 * `runs_read`: where a queued run is, and its result once done. A run is found only for the person, the connection and the workspace it was queued from; any other run,
 * and a made-up one, is `not_found`, so a token says nothing about runs that are not yours. The call is then admitted under the run's own ability, as the tool that queued
 * it was: a connection revoked or narrowed since is refused (`connection_denied`). Listed, and callable, only while the app registers a queued tool.
 */
#[IsReadOnly]
final class ReadRun extends Tool implements ServedBeside
{
    protected string $name = 'runs_read';

    protected string $description = 'Read a queued run started by another tool: its status (queued, running, done, failed, expired), its current step and progress while running, and its result once done. Wait poll_after_seconds between reads.';

    /** Served only beside a queued tool: without one there is no run to read. */
    public static function served(array $tools): bool
    {
        foreach ($tools as $tool) {
            if (is_subclass_of($tool, QueuedCapabilityTool::class)) {
                return true;
            }
        }

        return false;
    }

    /** Listed exactly when Registry::mcpTools() serves it. */
    public function shouldRegister(): bool
    {
        return in_array(self::class, app(Registry::class)->mcpTools(), true);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['run' => $schema->string()->description('The run token a tool answered with.')->required()];
    }

    public function handle(Request $request): mixed
    {
        $gate = app(ConsentGate::class);
        try {
            if (! $this->shouldRegister()) {
                throw new RuntimeException('capability_unavailable');
            }
            $arguments = $request->all();
            if (array_keys($arguments) !== ['run'] || ! is_string($arguments['run']) || preg_match('/^run_[0-9a-z]{26}$/', $arguments['run']) !== 1) {
                throw new RuntimeException(array_diff(array_keys($arguments), ['run']) !== [] ? 'unknown_arguments' : 'not_found');
            }
            // The principal BindMcpConnection bound from the token; admit() below verifies it against the live token and connection.
            $principal = request()->attributes->get('mcp.principal');
            $workspace = Workspaces::current();
            if (! $principal instanceof Principal || $principal->connectionId === null || $workspace === null) {
                throw new RuntimeException('connection_denied');
            }
            $run = DB::table('mcp_runs')->where('id', $arguments['run'])->where('actor_id', $principal->actorId)->where('connection_id', $principal->connectionId)
                ->where('workspace_id', (int) $workspace->getKey())->first();
            if ($run === null) {
                throw new RuntimeException('not_found');
            }
            $gate->admit((string) $run->ability);

            return Response::structured(self::view($run));
        } catch (Throwable $e) {
            return Response::error($gate->refusal($e)['error']);
        }
    }

    /** @return array<string, mixed> */
    private static function view(object $run): array
    {
        if (Carbon::parse($run->expires_at)->isPast()) {
            return ['run' => $run->id, 'status' => Runs::EXPIRED];
        }

        return match ($run->status) {
            Runs::DONE => ['run' => $run->id, 'status' => Runs::DONE, 'result' => json_decode((string) $run->result, true, 512, JSON_THROW_ON_ERROR)],
            Runs::FAILED => ['run' => $run->id, 'status' => Runs::FAILED, 'error' => (string) $run->error],
            Runs::RUNNING => ['run' => $run->id, 'status' => Runs::RUNNING, 'step' => $run->step, 'done' => $run->done === null ? null : (int) $run->done,
                'total' => $run->total === null ? null : (int) $run->total, 'poll_after_seconds' => Runs::pollAfter()],
            default => ['run' => $run->id, 'status' => Runs::QUEUED, 'poll_after_seconds' => Runs::pollAfter()],
        };
    }
}
