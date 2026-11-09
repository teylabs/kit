<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\ChecklistItem;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\ClientTimezone;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The "You" card: the viewer's photo, name, local time and timezone, with Edit (their profile), and their own onboarding steps until every one is done. The
 * card shows no title bar; `title()` stays its accessible name. `email`, `role` and `workspace` are still sent for an app's own card.
 */
#[TypeScript]
final class YouWidget extends Widget
{
    public function title(): string
    {
        return 'You';
    }

    public function component(): string
    {
        return 'you';
    }

    /** @return 'main'|'aside' */
    public function region(): string
    {
        return 'aside';
    }

    /** @return array{name: string, email: string, initials: string, avatar: array{url: string, url_2x: string}|null, role: string|null, workspace: string, timezone: string} the viewer, with the zone their times show in (`ClientTimezone::forViewer`) */
    public function person(): array
    {
        $user = RequestMemo::user($this->context->principal->actorId) ?? throw new \RuntimeException('workspace_not_found');
        // The viewer's first role in this workspace (the tenant context scopes it), by its configured label.
        $role = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() : null;

        return [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'initials' => app(PageShell::class)->initials((string) $user->name),
            'avatar' => Avatars::urls((int) $user->getKey()),
            'role' => $role === null ? null : (string) (config('concierge.roles.'.$role.'.label') ?? $role),
            'workspace' => (string) Workspaces::required()->name,
            'timezone' => ClientTimezone::forViewer($this->context->request),
        ];
    }

    /** @return list<ChecklistItem> the viewer's personal onboarding steps (scope `person`), or none once every one is done */
    public function checklist(): array
    {
        return Onboarding::checklist($this->context->principal->actorId, $this->context->workspace, 'person');
    }

    public function actions(): array
    {
        return [new Action('Edit', '/me/profile', 'get', key: 'profile')];
    }
}
