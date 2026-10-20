<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Sections/Index/Page.vue: a settings landing page's sections, grouped. */
#[TypeScript]
final class SectionsViewModel extends Data
{
    /** @param list<array{label: string, sections: list<array{label: string, description: string, url: string}>}> $groups */
    public function __construct(public string $title, public array $groups) {}
}
