<?php

namespace Tey\Kit\Features\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Workspaces\Slugs;

/**
 * The kit's workspace behaviour: slugs, the member-of scope and Concierge's naming and admission. The kit's own model uses it,
 * and a host that keeps its own workspace model (bound as `concierge.models.workspace` and `multitenancy.tenant_model`) uses it too.
 */
trait IsKitWorkspace
{
    public static function bootIsKitWorkspace(): void
    {
        // A slug is the first path segment of a workspace's pages: made from the name, and never reserved or taken.
        static::creating(function (Model $workspace): void {
            $workspace->slug = ($workspace->slug ?? '') === '' ? Slugs::unique((string) $workspace->name) : $workspace->slug;
            Slugs::assertAvailable($workspace->slug);
        });
        static::updating(function (Model $workspace): void {
            if ($workspace->isDirty('slug')) {
                Slugs::assertAvailable((string) $workspace->slug, (int) $workspace->getKey());
            }
        });
    }

    /**
     * Mirrors the `admitted` column's default (true in the kit's migration), so an unsaved or just-created model answers admission the way its row will, instead of
     * `null` until it is read back (which refused a workspace created in the same request as `admission_required`). Admission stays distinct from creation: a host whose
     * adopted column defaults to false sets `admitted` to false in its model's `$attributes`, and then admits explicitly.
     */
    public function initializeIsKitWorkspace(): void
    {
        if (! array_key_exists('admitted', $this->attributes) && $this->carriesAdmission()) {
            $this->attributes['admitted'] = true;
        }
    }

    /** Whether this model's table has the `admitted` column (a host table adopted before the column, or a stand-in without it, must not be given one); checked once per table. */
    private function carriesAdmission(): bool
    {
        // Remembered once it is known to be there; a table not migrated yet (a model made at boot) is asked again next time.
        static $tables = [];
        $table = $this->getTable();
        if ($tables[$table] ?? false) {
            return true;
        }
        try {
            return $tables[$table] = Schema::connection($this->getConnectionName())->hasColumn($table, 'admitted');
        } catch (\Throwable) {
            return false;
        }
    }

    /** The workspaces a user has an active membership in. A workspace being deleted (closed) is never one of them: it is listed nowhere. */
    public function scopeMemberOf(Builder $query, Member $user): void
    {
        $query->whereIn($this->getQualifiedKeyName(), ConciergeModels::membership()::query()->where('user_id', $user->getKey())->where('status', MembershipStatus::Active)->select('workspace_id'));
        if ($this->carriesDeletion()) {
            $query->whereNull($this->qualifyColumn('deleting_at'));
        }
    }

    /** Closed for a deferred deletion (`deleting_at` set): no one is admitted and no one joins until the host's job finishes it. Never reopened implicitly. */
    public function isDeleting(): bool
    {
        return $this->getAttribute('deleting_at') !== null;
    }

    /** Whether this model's table has `deleting_at` (a host that has not run the kit's migration yet has no closed workspaces); checked once per table. */
    private function carriesDeletion(): bool
    {
        static $tables = [];
        $table = $this->getTable();
        if ($tables[$table] ?? false) {
            return true;
        }
        try {
            return $tables[$table] = Schema::connection($this->getConnectionName())->hasColumn($table, 'deleting_at');
        } catch (\Throwable) {
            return false;
        }
    }

    public function conciergeName(): string
    {
        return (string) $this->name;
    }

    /** Admitted and not being deleted: a closed workspace takes no invitation and no new member (Concierge's `workspace_unavailable`). */
    public function conciergeAcceptsMembers(): bool
    {
        return (bool) $this->admitted && ! $this->isDeleting();
    }

    public static function required(): static
    {
        return static::current() ?? throw new \RuntimeException('workspace_required');
    }
}
