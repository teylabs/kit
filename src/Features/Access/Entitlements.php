<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * What a workspace's plan includes, per entitlement a feature declared (`Registry::entitlement`): whether the capability is included, and the limits it is capped at.
 * Precedence: the workspace's row in `kit_entitlements` (written by a billing integration or an operator), then the app's default (`kit.entitlements`), then not
 * included: a declared entitlement nobody configured fails closed. It is a third gate beside capabilities (is the feature on here) and permissions (may this person
 * do it), and it knows nothing about billing. A capability no entitlement gates is always included.
 */
final class Entitlements
{
    /** @var ?array<string, mixed> answers for the request that began with `remember()`; null (the default) decides afresh every time */
    private ?array $memo = null;

    /** The request the memo belongs to: a different one starts afresh, so a long-lived process never answers from the last request's memory. */
    private ?int $request = null;

    /** Begins a request: the workspace's rows are read once from here (the shell, the page's layout and the page share them), starting empty. */
    public function remember(): void
    {
        $this->memo = [];
        $this->request = spl_object_id(request());
    }

    public function included(string $capability, Workspace $workspace): bool
    {
        if (app(Registry::class)->entitlementFor($capability) === null) {
            return true;
        }
        $row = $this->row($capability, $workspace);

        return $row !== null ? $row['included'] : (bool) (config('kit.entitlements', [])[$capability]['included'] ?? false);
    }

    /** The cap on a declared limit here, or null for unlimited. Zero when the plan does not include the capability at all. */
    public function limit(string $capability, string $limit, Workspace $workspace): ?int
    {
        $this->assertLimit($capability, $limit);
        if (! $this->included($capability, $workspace)) {
            return 0;
        }
        $row = $this->row($capability, $workspace);
        $limits = $row !== null && $row['limits'] !== null ? $row['limits'] : (config('kit.entitlements', [])[$capability]['limits'] ?? []);
        // A limit neither the row nor the default names is unlimited: the plan included the capability without capping it.
        $value = $limits[$limit] ?? null;

        return $value === null ? null : max(0, (int) $value);
    }

    /** Refuses (`limit_reached`) adding `adding` when `current` already uses the cap; the server's check, so a count is never trusted from the browser. */
    public function enforce(string $capability, string $limit, Workspace $workspace, int $current, int $adding = 1): void
    {
        $cap = $this->limit($capability, $limit, $workspace);
        if ($cap !== null && $current + $adding > $cap) {
            throw new RuntimeException('limit_reached');
        }
    }

    /** The upgrade action for a capability the plan leaves out, when its entitlement opted into one; null when it is included or stays absent. */
    public function upgrade(string $capability, Workspace $workspace): ?Action
    {
        $upgrade = app(Registry::class)->entitlementFor($capability)['upgrade'] ?? null;
        if ($upgrade === null || $this->included($capability, $workspace)) {
            return null;
        }

        return new Action($upgrade['label'], WorkspaceUrl::to($workspace, $upgrade['suffix']), 'get');
    }

    /**
     * Sets what the workspace's plan includes for one entitlement, or clears it back to the app default (`null`). The one write a billing integration makes.
     *
     * @param  ?array<string, ?int>  $limits  limit => cap (null is unlimited); null keeps the app default's limits
     */
    public function set(string $capability, Workspace $workspace, ?bool $included, ?array $limits = null): void
    {
        foreach (array_keys($limits ?? []) as $limit) {
            $this->assertLimit($capability, (string) $limit);
        }
        if (app(Registry::class)->entitlementFor($capability) === null) {
            throw new RuntimeException('unknown_entitlement');
        }
        $where = ['workspace_id' => $workspace->getKey(), 'entitlement' => $capability];
        if ($included === null) {
            DB::table('kit_entitlements')->where($where)->delete();
        } else {
            DB::table('kit_entitlements')->upsert([$where + ['included' => $included, 'limits' => $limits === null ? null : json_encode($limits, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]], ['workspace_id', 'entitlement'], ['included', 'limits', 'updated_at']);
        }
        $this->memo = $this->memo === null ? null : [];
    }

    /** @return ?array{included: bool, limits: ?array<string, ?int>} */
    private function row(string $capability, Workspace $workspace): ?array
    {
        $rows = $this->memo === null || $this->request !== spl_object_id(request())
            ? DB::table('kit_entitlements')->where(['workspace_id' => $workspace->getKey(), 'entitlement' => $capability])->get(['entitlement', 'included', 'limits'])->all()
            // In a request, the workspace's rows are read once, for every entitlement.
            : ($this->memo['rows:'.$workspace->getKey()] ??= DB::table('kit_entitlements')->where('workspace_id', $workspace->getKey())->get(['entitlement', 'included', 'limits'])->all());
        foreach ($rows as $row) {
            if ($row->entitlement === $capability) {
                return ['included' => (bool) $row->included, 'limits' => $row->limits === null ? null : json_decode($row->limits, true, flags: JSON_THROW_ON_ERROR)];
            }
        }

        return null;
    }

    private function assertLimit(string $capability, string $limit): void
    {
        if (! in_array($limit, app(Registry::class)->entitlementFor($capability)['limits'] ?? [], true)) {
            throw new RuntimeException('unknown_entitlement_limit');
        }
    }
}
