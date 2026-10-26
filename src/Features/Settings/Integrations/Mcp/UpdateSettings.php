<?php

namespace Tey\Kit\Features\Settings\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Mcp\GatedByCapability;
use Tey\Kit\Features\Settings\Operations\WorkspaceProfile;
use Throwable;

final class UpdateSettings extends Tool
{
    use GatedByCapability;

    public const ABILITY = 'settings.write';

    protected string $name = 'workspace_settings_update';

    protected string $description = 'Update settings using the current version and stable intent key.';

    public function schema(JsonSchema $schema): array
    {
        return ['display_name' => $schema->string()->required(), 'timezone' => $schema->string()->required(), 'expected_version' => $schema->integer()->min(1)->required(), 'intent_key' => $schema->string()->required()];
    }

    public function handle(Request $request): mixed
    {
        try {
            return Response::structured(app(WorkspaceProfile::class)->update(app(ConsentGate::class)->admit('settings.write')[0], $request->all())->toArray());
        } catch (Throwable $e) {
            return Response::error(app(ConsentGate::class)->refusal($e)['error']);
        }
    }
}
