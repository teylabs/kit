<?php

namespace Tey\Kit\Features\Workspaces\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;
use Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace;

/** The default workspace model. A host with its own workspaces table binds its own model instead; see `Tey\Kit\Features\Workspaces\Workspaces`. */
class Workspace extends Model implements \Tey\Concierge\Contracts\Workspace, IsTenant
{
    use ImplementsTenant, IsKitWorkspace, UsesMultitenancyConfig;

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
}
