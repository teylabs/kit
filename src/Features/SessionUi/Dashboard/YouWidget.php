<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Workspaces\Workspaces;

/** The "You" card: the viewer, their role here and the workspace, with a link to their profile. */
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

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData(md: 3, lg: 4);
    }

    /** @return array{name: string, email: string, initials: string, avatar: array{url: string, url_2x: string}|null, role: string|null, workspace: string} */
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
        ];
    }

    public function actions(): array
    {
        return [new Action('Edit profile', '/me/profile', 'get', key: 'profile')];
    }
}
