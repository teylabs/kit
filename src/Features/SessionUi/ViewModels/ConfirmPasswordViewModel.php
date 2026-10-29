<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Auth/ConfirmPassword/Page.vue: where Cancel goes, always on the app (SafeRedirect). */
#[TypeScript]
final class ConfirmPasswordViewModel extends PageViewModel
{
    public function __construct(private string $title, private string $cancel) {}

    public function title(): string
    {
        return $this->title;
    }

    public function cancel(): string
    {
        return $this->cancel;
    }
}
