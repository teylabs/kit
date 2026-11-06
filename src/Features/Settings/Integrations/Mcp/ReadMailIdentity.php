<?php

namespace Tey\Kit\Features\Settings\Integrations\Mcp;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Mcp\GatedByCapability;
use Tey\Kit\Features\Settings\Mail\WorkspaceMailIdentity;
use Throwable;

/** Parity with Settings → Email sending: the sender name, from mode and address, the custom domain's status and DNS records, and the reply-to. */
final class ReadMailIdentity extends Tool
{
    use GatedByCapability;

    public const ABILITY = 'settings.read';

    protected string $name = 'workspace_mail_identity_read';

    protected string $description = 'Read the approved workspace\'s email sending settings: sender name, from address, custom domain status and DNS records, and reply-to.';

    public function handle(Request $request): mixed
    {
        if ($request->all() !== []) {
            return Response::error('unknown_arguments');
        }
        try {
            return Response::structured(app(WorkspaceMailIdentity::class)->read(app(ConsentGate::class)->admit('settings.read')[0])->toArray());
        } catch (Throwable $e) {
            return Response::error(app(ConsentGate::class)->refusal($e)['error']);
        }
    }
}
