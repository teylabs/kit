<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Dashboard/Page.vue, and for an app's own dashboard component: the workspace and the one obvious next step. */
#[TypeScript]
final class DashboardViewModel extends PageViewModel
{
    public function __construct(private string $title, private string $workspace, private string $next_label, private string $next_url) {}

    public function title(): string
    {
        return $this->title;
    }

    public function workspace(): string
    {
        return $this->workspace;
    }

    public function next_label(): string
    {
        return $this->next_label;
    }

    public function next_url(): string
    {
        return $this->next_url;
    }

}
