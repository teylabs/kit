<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/**
 * Props for pages/Auth/Register/Page.vue. With the defaults it is Fortify's open registration (`/register`, the kit's registerView): name, email and the two
 * passwords, posted to Fortify, which creates the account with the host's CreatesNewUsers action. A host's own sign-up flow (an invited account, say) renders
 * the same page from its own route with its own view model values, so its copy of the page stays the kit's:
 *
 * - `action`, `heading`, `lead` and `submitLabel`: where the form posts, and the card's copy;
 * - `email`: when set, the address is shown as text (with `emailHint` under it) and there is no email input;
 * - `submit`: values posted with the form that are not inputs (the shown `email`, say), added in <Form>'s transform;
 * - `throttleMessage`: the in-card copy for a 429, in place of the kit's `form.throttled` copy.
 *
 * An error on a key the page has no input for (`invitation`, or `email` when it is shown as text) is shown once, as an alert at the top of the card.
 */
#[TypeScript]
final class RegisterViewModel extends PageViewModel
{
    /**
     * @param  array<string, string>  $submit  values posted with the form that are not inputs
     * @param  ?string  $login  the "Sign in" link (null leaves it out)
     */
    public function __construct(
        private string $title,
        private string $heading = 'Create an account',
        private ?string $lead = 'Enter your details below to create your account',
        private string $action = '/register',
        private string $submitLabel = 'Create account',
        private ?string $email = null,
        private ?string $emailHint = null,
        private array $submit = [],
        private ?string $throttleMessage = null,
        private ?string $login = '/login',
        private ?string $status = null,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function heading(): string
    {
        return $this->heading;
    }

    public function lead(): ?string
    {
        return $this->lead;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function submitLabel(): string
    {
        return $this->submitLabel;
    }

    /** The address, shown as text in place of the email input; null where the person types it. */
    public function email(): ?string
    {
        return $this->email;
    }

    public function emailHint(): ?string
    {
        return $this->emailHint;
    }

    /** @return array<string, string> */
    public function submit(): array
    {
        return $this->submit;
    }

    public function throttleMessage(): ?string
    {
        return $this->throttleMessage;
    }

    public function login(): ?string
    {
        return $this->login;
    }

    public function status(): ?string
    {
        return $this->status;
    }
}
