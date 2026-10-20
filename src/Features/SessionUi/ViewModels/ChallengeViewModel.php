<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for the guest pages Fortify asks for: the two-factor challenge (pages/Auth/TwoFactor/Page.vue) and the email verification notice (pages/Auth/VerifyEmail/Page.vue). */
#[TypeScript]
final class ChallengeViewModel extends Data
{
    public function __construct(public string $title, public ?string $status = null, public ?string $email = null) {}
}
