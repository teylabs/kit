<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Auth/Login/Page.vue. */
#[TypeScript]
final class LoginViewModel extends Data
{
    public function __construct(public string $title) {}
}
