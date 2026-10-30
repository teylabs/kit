<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Auth/Login/Page.vue. */
#[TypeScript]
final class LoginViewModel extends PageViewModel
{
    /**
     * @param  ?array{url: string, email: string}  $localLogin  an app's one-click local sign-in offer (null where it is not available)
     * @param  ?string  $register  the "Create an account" link, while Fortify's registration feature is on (null where registration is closed)
     */
    public function __construct(private string $title, private ?string $status = null, private ?array $localLogin = null, private ?string $register = null) {}

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

    public function register(): ?string
    {
        return $this->register;
    }
}
