<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

/** Props for the Transfer ownership modal (pages/Workspace/Settings/People/Transfer/Page.vue): who receives it, the name to type, and where the transfer goes. */
#[TypeScript]
final class TransferOwnershipViewModel extends ViewModel
{
    public function __construct(private string $title, private int $membership, private string $recipient, private string $workspace_name, private string $demoted_to, private Action $submit) {}

    public function title(): string
    {
        return $this->title;
    }

    public function membership(): int
    {
        return $this->membership;
    }

    public function recipient(): string
    {
        return $this->recipient;
    }

    public function workspace_name(): string
    {
        return $this->workspace_name;
    }

    public function demoted_to(): string
    {
        return $this->demoted_to;
    }

    public function submit(): Action
    {
        return $this->submit;
    }
}
