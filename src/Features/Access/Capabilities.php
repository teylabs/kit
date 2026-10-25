<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * Whether a capability (a feature of the app) is on for a person in a workspace. Precedence: the person's own override in that workspace, then the workspace's (both set by
 * an operator and kept in `kit_capability_overrides`), then the capability's own default (`kit.capabilities`, or a plan's, through Pennant). What is off is absent everywhere it would appear: a 404, no navigation link, no
 * settings section, no MCP tool. It is a gate separate from permissions: a page needs both the capability on and the permission.
 */
final class Capabilities
{
    /** @var ?array<string, bool> answers for the request that began with `remember()`; null (the default) decides afresh every time */
    private ?array $memo = null;

    /** The request the memo belongs to: a different one starts afresh, so a long-lived process (a test, a worker) never answers from the last request's memory. */
    private ?int $request = null;

    /** Begins a request: answers are memoized from here (the shell, the page's layout and the page share them), starting empty. */
    public function remember(): void
    {
        $this->memo = [];
        $this->request = spl_object_id(request());
    }

    public function active(string $capability, Workspace $workspace, ?Model $user = null): bool
    {
        if ($this->memo === null || $this->request !== spl_object_id(request())) {
            return $this->decide($capability, $workspace, $user);
        }

        return $this->memo[$capability.':'.$workspace->getKey().':'.($user?->getKey() ?? 0)] ??= $this->decide($capability, $workspace, $user);
    }

    private function decide(string $capability, Workspace $workspace, ?Model $user): bool
    {
        $rows = $this->memo === null || $this->request !== spl_object_id(request())
            ? DB::table('kit_capability_overrides')->where(['workspace_id' => $workspace->getKey(), 'capability' => $capability])->whereIn('user_id', array_filter([0, $user?->getKey()], fn ($id) => $id !== null))->pluck('enabled', 'user_id')
            // In a request, the workspace's overrides are read once, for every capability and person.
            : collect($this->memo['overrides:'.$workspace->getKey()] ??= DB::table('kit_capability_overrides')->where('workspace_id', $workspace->getKey())->get(['capability', 'user_id', 'enabled'])->all())
                ->where('capability', $capability)->whereIn('user_id', array_filter([0, $user?->getKey()], fn ($id) => $id !== null))->pluck('enabled', 'user_id');
        if ($user !== null && $rows->has($user->getKey())) {
            return (bool) $rows[$user->getKey()];
        }
        if ($rows->has(0)) {
            return (bool) $rows[0];
        }

        return Feature::for($workspace)->active($capability);
    }

    /** @return list<string> every capability the features declared */
    public function known(): array
    {
        return app(Registry::class)->workspaceCapabilities();
    }

    /** Switches a capability for the whole workspace (`true`/`false`), or back to its default (`null`). */
    public function setForWorkspace(string $capability, Workspace $workspace, ?bool $enabled): void
    {
        $this->write($capability, $workspace, 0, $enabled);
    }

    /** Switches a capability for one person in one workspace, or back to following the workspace (`null`). */
    public function setForPerson(string $capability, Workspace $workspace, int $userId, ?bool $enabled): void
    {
        $this->write($capability, $workspace, $userId, $enabled);
    }

    private function write(string $capability, Workspace $workspace, int $userId, ?bool $enabled): void
    {
        $this->assertKnown($capability);
        $where = ['workspace_id' => $workspace->getKey(), 'user_id' => $userId, 'capability' => $capability];
        if ($enabled === null) {
            DB::table('kit_capability_overrides')->where($where)->delete();
        } else {
            DB::table('kit_capability_overrides')->upsert([$where + ['enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]], ['workspace_id', 'user_id', 'capability'], ['enabled', 'updated_at']);
        }
        $this->memo = $this->memo === null ? null : [];
    }

    /** @return list<array{capability: string, workspace: bool, people: array<int, bool>}> what is set explicitly in a workspace, for an operator to read */
    public function overview(Workspace $workspace): array
    {
        $rows = DB::table('kit_capability_overrides')->where('workspace_id', $workspace->getKey())->get()->groupBy('capability');

        return array_map(fn (string $capability): array => [
            'capability' => $capability,
            'workspace' => $this->active($capability, $workspace),
            'people' => ($rows[$capability] ?? collect())->where('user_id', '!=', 0)->mapWithKeys(fn ($row): array => [(int) $row->user_id => (bool) $row->enabled])->all(),
        ], $this->known());
    }

    private function assertKnown(string $capability): void
    {
        if (! in_array($capability, $this->known(), true)) {
            throw new RuntimeException('unknown_capability');
        }
    }
}
