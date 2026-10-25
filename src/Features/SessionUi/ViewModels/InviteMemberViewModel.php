<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

/** Props for the Invite member modal (pages/Workspace/Settings/People/Invite/Page.vue): who may be invited as what, and where the invitation is sent. */
#[TypeScript]
final class InviteMemberViewModel extends ViewModel
{
    /** @param  list<array{value: string, label: string}>  $roles  the roles the viewer may grant */
    public function __construct(private string $title, private array $roles, private Action $submit) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{value: string, label: string}> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function submit(): Action
    {
        return $this->submit;
    }
}
