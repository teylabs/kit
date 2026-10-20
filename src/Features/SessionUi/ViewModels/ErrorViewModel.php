<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Error/Page.vue. */
#[TypeScript]
final class ErrorViewModel extends PageViewModel
{
    public function __construct(private string $title, private int $status, private string $message, private string $recovery_url, private string $recovery_label) {}

    public function title(): string
    {
        return $this->title;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function recovery_url(): string
    {
        return $this->recovery_url;
    }

    public function recovery_label(): string
    {
        return $this->recovery_label;
    }

}
