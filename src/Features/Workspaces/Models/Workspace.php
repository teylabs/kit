<?php

namespace Tey\Kit\Features\Workspaces\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Workspaces\Slugs;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

class Workspace extends Model implements \Tey\Concierge\Contracts\Workspace, IsTenant
{
    use ImplementsTenant, UsesMultitenancyConfig;

    public $timestamps = false;

    /** The table has no timestamps, so `oldest()` and `latest()` order by the key (Concierge's first local owner asks for the oldest workspace; PostgreSQL refuses a missing column). */
    public function getCreatedAtColumn(): string
    {
        return $this->getKeyName();
    }

    public function getUpdatedAtColumn(): string
    {
        return $this->getKeyName();
    }

    protected $guarded = [];

    protected static function booted(): void
    {
        // A slug is the first path segment of a workspace's pages: made from the name, and never reserved or taken.
        static::creating(function (self $workspace): void {
            $workspace->slug = ($workspace->slug ?? '') === '' ? Slugs::unique((string) $workspace->name) : $workspace->slug;
            Slugs::assertAvailable($workspace->slug);
        });
        static::updating(function (self $workspace): void {
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
        return $this->name;
    }

    public function conciergeAcceptsMembers(): bool
    {
        return (bool) $this->admitted;
    }

    public static function required(): self
    {
        return static::current() ?? throw new \RuntimeException('workspace_required');
    }
}
