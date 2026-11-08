<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Facades\DB;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\InvitationStatus;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Data\Action;

/**
 * What the workspace list (`/me/workspaces`) and the no-workspace page read about the signed-in person, outside any workspace: their role in and the member count of
 * each workspace they belong to (a few queries for the whole list, never one per workspace), the invitations open to their email, and the create action provisioning allows.
 * Membership (who is in) is all it reads: no capability, entitlement or admission is decided here; opening a workspace still goes through its own gate.
 */
final class WorkspaceChooser
{
    /**
     * Start a workspace: only with `kit.workspaces.provisioning` = `self_serve` (never `invite_only` or anything unknown) and only while the host registers a GET
     * route at `/me/workspaces/create`. The kit ships no creation flow, so without the host's own route there is nothing to offer (fail closed: never a link to a 404).
     */
    public static function createAction(string $label = 'Create a workspace'): ?Action
    {
        if (config('kit.workspaces.provisioning') !== 'self_serve' || ! isset(app('router')->getRoutes()->getRoutesByMethod()['GET']['me/workspaces/create'])) {
            return null;
        }

        return new Action($label, '/me/workspaces/create', 'get');
    }

    /**
     * The person's active memberships in `$ids`, each with its id, its roles (Concierge's order, best first), whether it owns the workspace, and the workspace's
     * active member count. Workspaces they are not an active member of are absent: an id from elsewhere never reads another workspace's data.
     *
     * @param  list<int>  $ids
     * @return array<int, array{membership: int, roles: list<string>, owner: bool, members: int}>
     */
    public static function memberships(Member $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $memberships = ConciergeModels::membership()::query()->where('user_id', $user->getKey())->where('status', MembershipStatus::Active)->whereIn('workspace_id', $ids)->pluck('id', 'workspace_id')->all();
        if ($memberships === []) {
            return [];
        }
        $mine = array_map('intval', array_keys($memberships));
        $counts = ConciergeModels::membership()::query()->where('status', MembershipStatus::Active)->whereIn('workspace_id', $mine)->groupBy('workspace_id')->selectRaw('workspace_id, count(*) as aggregate')->pluck('aggregate', 'workspace_id')->all();
        $owned = ConciergeModels::ownership()::query()->whereIn('workspace_id', $mine)->whereIn('membership_id', array_values($memberships))->pluck('membership_id', 'workspace_id')->all();
        $roles = self::roles($user, $mine);
        $rows = [];
        foreach ($memberships as $workspace => $membership) {
            $rows[(int) $workspace] = ['membership' => (int) $membership, 'roles' => Roles::sort($roles[(int) $workspace] ?? []), 'owner' => (int) ($owned[$workspace] ?? 0) === (int) $membership, 'members' => (int) ($counts[$workspace] ?? 0)];
        }

        return $rows;
    }

    /** A role's configured label, else its name. */
    public static function roleLabel(?string $role): ?string
    {
        return $role === null ? null : (string) (config('concierge.roles.'.$role.'.label') ?? $role);
    }

    /**
     * The person's role names in each workspace, from Spatie's team-scoped pivot in one query. Without teams there is no per-workspace role to read: none.
     *
     * @param  list<int>  $workspaces
     * @return array<int, list<string>>
     */
    private static function roles(Member $user, array $workspaces): array
    {
        $team = config('permission.column_names.team_foreign_key');
        $pivot = config('permission.table_names.model_has_roles');
        $table = config('permission.table_names.roles');
        if (! config('permission.teams') || ! is_string($team) || ! is_string($pivot) || ! is_string($table) || ! method_exists($user, 'getMorphClass')) {
            return [];
        }
        $morph = (string) (config('permission.column_names.model_morph_key') ?: 'model_id');
        $rows = DB::table($pivot)->join($table, $table.'.id', '=', $pivot.'.'.(config('permission.column_names.role_pivot_key') ?: 'role_id'))
            ->where($pivot.'.'.$morph, $user->getKey())->where($pivot.'.model_type', $user->getMorphClass())->whereIn($pivot.'.'.$team, $workspaces)
            ->get([$pivot.'.'.$team.' as workspace', $table.'.name as name']);
        $roles = [];
        foreach ($rows as $row) {
            $roles[(int) $row->workspace][] = (string) $row->name;
        }

        return $roles;
    }

    /**
     * Invitations sent to the person's email that are still open, newest first (at most ten). Accepting one is the link in the email they were sent: its token is
     * never stored in the clear, so there is no accept or decline from here.
     *
     * @return list<array{workspace: string, inviter: ?string, message: ?string, expires: ?string}>
     */
    public static function invitations(string $email): array
    {
        return ConciergeModels::invitation()::query()->with(['workspace', 'inviter'])
            ->where('email', mb_strtolower(trim($email)))->where('status', InvitationStatus::Pending)->where('expires_at', '>', now())
            ->orderByDesc('id')->limit(10)->get()
            ->map(fn ($invitation): array => ['workspace' => (string) ($invitation->workspace?->conciergeName() ?? config('app.name')), 'inviter' => $invitation->inviter?->name, 'message' => $invitation->message, 'expires' => $invitation->expires_at?->toIso8601String()])->all();
    }
}
