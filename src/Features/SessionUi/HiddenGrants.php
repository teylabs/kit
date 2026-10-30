<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\MembersIndex;

/**
 * `kit.people.hide_grants`: the Concierge grants (roles) whose holders the kit's people lists leave out, such as a support role. A grant is a Spatie role
 * assigned to the user in the workspace's team, so a membership is hidden when its user holds one of these roles there. People, the People widget,
 * people search and a member's own pages and actions by id all apply it, so a hidden member shows up in none of them (and is a 404 by URL).
 */
final class HiddenGrants
{
    /** @return list<string> the grant names to hide; anything but a list of names is refused, never read loosely */
    public static function names(): array
    {
        $names = config('kit.people.hide_grants', []);
        if (! is_array($names) || ! array_is_list($names) || array_filter($names, fn ($name): bool => ! is_string($name) || $name === '') !== []) {
            throw new RuntimeException('kit.people.hide_grants must be a list of grant names.');
        }

        return $names;
    }

    /**
     * Refuses, at boot, hidden grants this Concierge can't apply: People needs `MembersIndex::scopeMembersUsing()` (0.9.3 or later) to leave them out of its
     * rows and count, so the app fails to start rather than any page showing everyone or failing. With none to hide, any Concierge will do.
     *
     * @param  class-string  $index  the members index to check; the test names one without the method
     */
    public static function assertUsable(string $index = MembersIndex::class): void
    {
        if (self::names() !== [] && ! method_exists($index, 'scopeMembersUsing')) {
            throw new RuntimeException('kit.people.hide_grants needs tey/concierge 0.9.3 or later');
        }
    }

    /** Concierge's members index with the hidden grants left out of its rows and counts; with none to hide, as it was. */
    public static function index(MembersIndex $index): MembersIndex
    {
        if (self::names() === []) {
            return $index;
        }

        return $index->scopeMembersUsing(fn (Builder $memberships, Workspace $workspace) => self::scope($memberships, $workspace->getKey()));
    }

    /** Leaves out of a query on Concierge's memberships table every membership whose user holds a hidden grant in the workspace. */
    public static function scope(Builder $memberships, int|string $workspace): void
    {
        $names = self::names();
        if ($names === []) {
            return;
        }
        $table = (new (ConciergeModels::membership()))->getTable();
        $pivot = Config::modelHasRolesTable();
        $roles = Config::rolesTable();

        $memberships->whereNotIn($table.'.user_id', DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.'.app(PermissionRegistrar::class)->pivotRole)
            ->where($pivot.'.model_type', (new (ConciergeModels::user()))->getMorphClass())
            ->whereIn($roles.'.name', $names)
            ->when(Config::teamsEnabled(), fn ($query) => $query->where($pivot.'.'.Config::teamForeignKey(), $workspace))
            ->select($pivot.'.'.Config::morphKey()));
    }
}
