<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\TableData;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Settings/People/Page.vue: the Members and Invitations tabs as one server-driven table, and the invite action when the viewer may use it. */
#[TypeScript]
final class PeopleViewModel extends PageViewModel
{
    /** @param  list<array{value: string, label: string}>  $roles  the roles the viewer may grant, for the invite and change-role dialogs */
    public function __construct(private string $title, private string $tab, private TableData $table, private ?Action $invite, private array $roles, private ?string $invite_link = null) {}

    public function title(): string
    {
        return $this->title;
    }

    /** `members` or `invitations`: the `?tab` of the URL. */
    public function tab(): string
    {
        return $this->tab;
    }

    public function table(): TableData
    {
        return $this->table;
    }

    /** Invite member: absent (the button is not there) when the viewer may grant no role. */
    public function invite(): ?Action
    {
        return $this->invite;
    }

    /** @return list<array{value: string, label: string}> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function invite_link(): ?string
    {
        return $this->invite_link;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }
}
