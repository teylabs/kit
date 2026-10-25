<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Auth/Login/Page.vue. */
#[TypeScript]
final class LoginViewModel extends PageViewModel
{
    /** @param  ?array{url: string, email: string}  $localLogin  an app's one-click local sign-in offer (null where it is not available) */
    public function __construct(private string $title, private ?string $status = null, private ?array $localLogin = null) {}

    public function title(): string
    {
        return $this->title;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    /** @return ?array{url: string, email: string} */
    public function localLogin(): ?array
    {
        return $this->localLogin;
    }
}
