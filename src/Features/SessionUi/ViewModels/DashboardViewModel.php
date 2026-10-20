<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Dashboard/Page.vue, and for an app's own dashboard component: the workspace and the one obvious next step. */
#[TypeScript]
final class DashboardViewModel extends Data
{
    public function __construct(public string $title, public string $workspace, public string $next_label, public string $next_url) {}
}
