<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Throwable;

/** The workspace's people, in a ScrollList; the card links to the People settings page for someone who may manage it. */
#[TypeScript]
final class PeopleWidget extends Widget
{
    public function title(): string
    {
        return 'People';
    }

    public function component(): string
    {
        return 'people';
    }

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData(md: 3, lg: 4);
    }

    /** The ScrollList prop this card renders. */
    public function list(): string
    {
        return (new PeopleList($this->context->request, $this->context->workspace))->key();
    }

    public function lists(): array
    {
        return [new PeopleList($this->context->request, $this->context->workspace)];
    }

    public function actions(): array
    {
        try {
            app(Access::class)->authorize($this->context->principal, 'members.manage');
        } catch (Throwable) {
            return [];
        }

        return [new Action('Manage people', WorkspaceUrl::to($this->context->workspace, 'settings/people', 'session-ui', $this->context->request), 'get', key: 'people')];
    }
}
