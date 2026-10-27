<?php

namespace Tey\Kit\Dashboard;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\SessionUi\Dashboard\Widgets;

/**
 * The dashboard as the person sees it: the workspace and each widget they may see, with its state and its own props. Read only. A scrolling list's rows
 * are not included (they page in the browser); a widget that fails is reported with `state: error`, as on the page. It spans the mcp and session-ui
 * features, so it lives outside both and the composition root registers it when both are adopted.
 */
#[Capability('workspace.dashboard')]
#[IsReadOnly]
final class ReadDashboard extends CapabilityTool
{
    public const ABILITY = 'dashboard.read';

    protected string $description = 'Read the workspace dashboard: the widgets you can see, their state and what they show.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        $current = app(Access::class)->authorize($principal, self::ABILITY);
        $widgets = app(Widgets::class)->resolve(request(), $principal, $workspace)['widgets'];

        return ['workspace' => ['id' => $workspace, 'name' => (string) $current->name], 'widgets' => $widgets];
    }
}
