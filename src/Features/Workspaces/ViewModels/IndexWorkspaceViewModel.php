<?php

namespace Tey\Kit\Features\Workspaces\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Index/Page.vue. */
#[TypeScript]
final class IndexWorkspaceViewModel extends PageViewModel
{
    /** @param list<array{id: int, name: string, leave: ?Action}> $workspaces  `leave` is absent for a workspace you own: transfer ownership first */
    public function __construct(private string $title, private array $workspaces) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: int, name: string, leave: ?Action}> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }
}
