<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Error/Page.vue. */
#[TypeScript]
final class ErrorViewModel extends Data
{
    public function __construct(public string $title, public int $status, public string $message, public string $recovery_url, public string $recovery_label) {}
}
