<?php

namespace Tey\Kit\Features\Workspaces\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Index/Page.vue. */
#[TypeScript]
final class IndexWorkspaceViewModel extends Data
{
    /** @param list<array{id: int, name: string}> $workspaces */
    public function __construct(public string $title, public array $workspaces) {}
}
