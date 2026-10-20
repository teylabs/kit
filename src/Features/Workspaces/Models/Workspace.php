<?php

namespace Tey\Kit\Features\Workspaces\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Workspaces\Slugs;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

class Workspace extends Model implements \Tey\Concierge\Contracts\Workspace, IsTenant
{
    use ImplementsTenant, UsesMultitenancyConfig;

    public $timestamps = false;

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
