<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\Data\WelcomeCardData;

/** Props for pages/Workspace/Dashboard/Page.vue, and for an app's own dashboard component: the workspace and the one obvious next step. */
#[TypeScript]
final class DashboardViewModel extends PageViewModel
{
    /** @param  string  $greeting  "Good morning, {first name}", in the person's timezone */
    public function __construct(private string $title, private string $workspace, private string $next_label, private string $next_url, private string $greeting = '', private ?WelcomeCardData $welcome = null) {}

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

    public function greeting(): string
    {
        return $this->greeting;
    }

    public function welcome(): ?WelcomeCardData
    {
        return $this->welcome;
    }

}
