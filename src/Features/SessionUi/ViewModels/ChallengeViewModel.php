<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for the guest pages Fortify asks for: the two-factor challenge (pages/Auth/TwoFactor/Page.vue) and the email verification notice (pages/Auth/VerifyEmail/Page.vue). */
#[TypeScript]
final class ChallengeViewModel extends PageViewModel
{
    public function __construct(private string $title, private ?string $status = null, private ?string $email = null) {}

    public function title(): string
    {
        return $this->title;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function email(): ?string
    {
        return $this->email;
    }
}
