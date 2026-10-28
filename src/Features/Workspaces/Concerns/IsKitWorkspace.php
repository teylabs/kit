<?php

namespace Tey\Kit\Features\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    /** The workspaces a user has an active membership in. */
    public function scopeMemberOf(Builder $query, Member $user): void
    {
        $query->whereIn($this->getQualifiedKeyName(), ConciergeModels::membership()::query()->where('user_id', $user->getKey())->where('status', MembershipStatus::Active)->select('workspace_id'));
    }

    public function conciergeName(): string
    {
        return (string) $this->name;
    }

    public function conciergeAcceptsMembers(): bool
    {
        return (bool) $this->admitted;
    }

    public static function required(): static
    {
        return static::current() ?? throw new \RuntimeException('workspace_required');
    }
}
