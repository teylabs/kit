<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Auth/ResetPassword/Page.vue: the emailed link's token and address, posted back with the new password, and the flashed `status` (FormStatus shows it). */
#[TypeScript]
final class ResetPasswordViewModel extends PageViewModel
{
    public function __construct(private string $title, private string $token, private string $email, private ?string $status = null) {}

    public function title(): string
    {
        return $this->title;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function status(): ?string
    {
        return $this->status;
    }
}
