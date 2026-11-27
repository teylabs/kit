<?php

namespace Tey\Kit\Features\Settings\Integrations\Mcp;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Mcp\GatedByCapability;
use Tey\Kit\Features\Settings\Setup\Setup;
use Throwable;

/** Parity with Settings → Setup: each setup row, its state, what is wrong and its fix. */
final class ReadSetup extends Tool
{
    use GatedByCapability;

    public const ABILITY = 'settings.write';

    protected string $name = 'workspace_setup_read';

    protected string $description = 'Read the approved workspace\'s setup checklist: mail, media, row-level security, queue and the steps done by hand, each with its state (ok, info, warning, failing, missing, held, overridden, manual) and fix. Owners and admins only; the app-wide rows only for an operator.';

    public function handle(Request $request): mixed
    {
        if ($request->all() !== []) {
            return Response::error('unknown_arguments');
        }
        try {
            $setup = app(Setup::class)->read(app(ConsentGate::class)->admit('settings.write')[0]);

            return Response::structured(['rows' => array_map(fn ($row) => $row->toArray(), $setup['rows']), 'app_rows' => $setup['app_rows']]);
        } catch (Throwable $e) {
            return Response::error(app(ConsentGate::class)->refusal($e)['error']);
        }
    }
}
