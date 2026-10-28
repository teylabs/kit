<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Lists\ListEmptyData;
use Tey\Kit\Features\Core\Lists\ListItemData;
use Tey\Kit\Features\Core\Lists\ScrollListViewModel;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Workspaces;

/** The workspace's active members by name, for the People widget. Scoped to the workspace it was built for; the list itself refuses any other. */
final class PeopleList extends ScrollListViewModel
{
    public function key(): string
    {
        return 'dashboard_people';
    }

    public function title(): string
    {
        return 'People';
    }

    protected function activeWorkspace(): int|string|null
    {
        return Workspaces::current()?->getKey();
    }

    protected function query(): Builder
    {
        $model = Users::model();
        $key = (new $model)->getKeyName();
        $members = ConciergeModels::membership()::query()->where('workspace_id', $this->workspace)->where('status', MembershipStatus::Active)->select('user_id');

        return $model::query()->whereIn($key, $members)->orderBy('name')->orderBy($key);
    }

    protected function row(Model $model): ListItemData
    {
        return new ListItemData((string) $model->getKey(), (string) $model->name, description: (string) $model->email);
    }

    protected function empty(): ListEmptyData
    {
        return new ListEmptyData('first-use', 'No one here yet', 'People you invite show up here once they join.');
    }
}
