<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

/** Props for the Change role modal (pages/Workspace/Settings/People/ChangeRole/Page.vue): the member's current role and the roles the viewer may give. */
#[TypeScript]
final class ChangeRoleViewModel extends ViewModel
{
    /** @param  list<array{value: string, label: string}>  $roles */
    public function __construct(private string $title, private ?string $role, private array $roles, private Action $submit) {}

    public function title(): string
    {
        return $this->title;
    }

    public function role(): ?string
    {
        return $this->role;
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
