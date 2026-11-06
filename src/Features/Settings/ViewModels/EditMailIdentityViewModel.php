<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Settings\Data\MailIdentityView;

/** Props for pages/Workspace/Settings/Email/Page.vue. */
#[TypeScript]
final class EditMailIdentityViewModel extends PageViewModel
{
    public function __construct(private string $title, private MailIdentityView $identity, private ?Action $save, private ?Action $check) {}

    public function title(): string
    {
        return $this->title;
    }

    public function identity(): MailIdentityView
    {
        return $this->identity;
    }

    /** Save: absent when the viewer's role can only read the settings. */
    public function save(): ?Action
    {
        return $this->save;
    }

    /** Check now: asks the provider to verify the custom domain. Absent for a read-only viewer. */
    public function check(): ?Action
    {
        return $this->check;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }
}
