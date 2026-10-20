<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Password/Edit/Page.vue: change your password (Fortify's update, on `update_url`). */
#[TypeScript]
final class PasswordViewModel extends Data
{
    public function __construct(public string $title, public string $update_url, public ?string $notice) {}
}
