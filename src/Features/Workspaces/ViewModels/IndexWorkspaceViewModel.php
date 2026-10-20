<?php

namespace Tey\Kit\Features\Workspaces\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Index/Page.vue. */
#[TypeScript]
final class IndexWorkspaceViewModel extends PageViewModel
{
    /** @param list<array{id: int, name: string}> $workspaces */
    public function __construct(private string $title, private array $workspaces) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: int, name: string}> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }

}
