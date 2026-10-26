<?php

namespace Tey\Kit\Features\Settings\Integrations\Mcp;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Mcp\GatedByCapability;
use Tey\Kit\Features\Settings\Operations\WorkspaceProfile;
use Throwable;

final class ReadSettings extends Tool
{
    use GatedByCapability;

    public const ABILITY = 'settings.read';

    protected string $name = 'workspace_settings_read';

    protected string $description = 'Read settings and current version of the approved workspace.';

    public function handle(Request $request): mixed
    {
        if ($request->all() !== []) {
            return Response::error('unknown_arguments');
        }
        try {
            return Response::structured(app(WorkspaceProfile::class)->read(app(ConsentGate::class)->admit('settings.read')[0])->toArray());
        } catch (Throwable $e) {
            return Response::error(app(ConsentGate::class)->refusal($e)['error']);
        }
    }
}
