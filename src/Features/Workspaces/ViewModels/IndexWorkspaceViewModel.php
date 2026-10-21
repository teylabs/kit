<?php

namespace Tey\Kit\Features\Workspaces\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Index/Page.vue. */
#[TypeScript]
final class IndexWorkspaceViewModel extends PageViewModel
{
    /** @param list<array{id: int, name: string, leave_url: ?string}> $workspaces  `leave_url` is null for a workspace you own: transfer ownership first */
    public function __construct(private string $title, private array $workspaces) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: int, name: string, leave_url: ?string}> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }

}
