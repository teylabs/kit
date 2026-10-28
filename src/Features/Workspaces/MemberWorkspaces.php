<?php

namespace Tey\Kit\Features\Workspaces;

use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Core\RequestMemo;

/** The signed-in person's workspaces, read once per request (RequestMemo). */
final class MemberWorkspaces
{
    /** @return list<Workspace> the workspaces the person has an active membership in, by id */
    public static function workspaces(object $user): array
    {
        return RequestMemo::instance()->get('workspaces:'.$user->getKey(), fn (): array => Workspaces::query()->memberOf($user)->orderBy('id')->get()->all());
    }

    /** One of the person's workspaces (by id) when they are an active member of it, else null: never another workspace, never for no person. */
    public static function workspace(int $id, ?object $user = null): ?Workspace
    {
        foreach ($user === null ? [] : self::workspaces($user) as $workspace) {
            if ((int) $workspace->getKey() === $id) {
                return $workspace;
            }
        }

        return null;
    }
}
