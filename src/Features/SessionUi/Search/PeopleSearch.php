<?php

namespace Tey\Kit\Features\SessionUi\Search;

use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Throwable;

/**
 * The kit's one generic provider: the workspace's active members whose name or email matches, by name. Everyone who can read the dashboard sees who is
 * in the workspace (as the People widget shows); only someone who manages people gets a link, to People filtered to that person.
 */
final class PeopleSearch implements SearchProvider
{
    public function search(SearchQuery $query): array
    {
        $model = Users::model();
        $key = (new $model)->getKeyName();
        $members = ConciergeModels::membership()::query()->where('workspace_id', $query->workspace)->where('status', MembershipStatus::Active)->select('user_id');
        $manages = $this->manages($query);

        return $model::query()->whereIn($key, $members)
            ->where(fn ($where) => $where->where('name', 'like', $query->like())->orWhere('email', 'like', $query->like()))
            ->orderBy('name')->orderBy($key)->limit($query->limit)->get()
            ->map(fn ($person): SearchResult => new SearchResult(
                (string) $person->getKey(),
                (string) $person->name,
                $manages ? WorkspaceUrl::to($query->workspace, 'settings/people?'.http_build_query(['filter' => ['search' => (string) $person->email]]), null, $query->request) : null,
                (string) $person->email,
            ))->values()->all();
    }

    private function manages(SearchQuery $query): bool
    {
        try {
            app(Access::class)->authorize($query->principal, 'members.manage');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
