<?php

namespace Tey\Kit\Features\Finalize;

use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Finalize\Integrations\Mcp\ConfirmFinalizeStep;
use Tey\Kit\Features\Finalize\Integrations\Mcp\FinalizeSubject;
use Tey\Kit\Features\Finalize\Integrations\Mcp\ReadFinalizeGate;
use Tey\Kit\Features\Finalize\Integrations\Mcp\UnfinalizeSubject;

/**
 * Finalize gates: a checklist that ends in a certified, recorded commit, and an un-finalize that is recorded too. The app registers its flows with
 * `Flows`; the kit keeps the confirmations and finalizations, computes every step on the server, and refuses a stale or incomplete commit. Four abilities
 * on one capability: `finalize.read`, `finalize.confirm`, `finalize.certify`, and `finalize.unfinalize`, its own permission, normally narrower.
 */
final class FinalizeProvider extends ServiceProvider implements Contributes
{
    public const array CODES = ['finalize_flow_unknown' => 404, 'finalize_subject_not_found' => 404, 'finalize_step_unknown' => 404, 'finalize_stale' => 409,
        'finalize_incomplete' => 409, 'finalize_finalized' => 409, 'finalize_not_finalized' => 409, 'finalize_step_not_ready' => 409, 'finalize_step_not_confirmable' => 422,
        'finalize_certify_required' => 422, 'finalize_reason_required' => 422, 'finalize_unfinalize_refused' => 403];

    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/finalize.php', 'web', $routes)
            ->scopes(['finalize.read' => 'Read finalize checklists', 'finalize.confirm' => 'Confirm finalize steps', 'finalize.certify' => 'Certify and finalize', 'finalize.unfinalize' => 'Un-finalize, with a reason'])
            ->abilities(['finalize.read' => 'workspace.finalize', 'finalize.confirm' => 'workspace.finalize', 'finalize.certify' => 'workspace.finalize', 'finalize.unfinalize' => 'workspace.finalize'])
            ->capabilities(['workspace.finalize'])
            ->permission('workspace.finalize.view', ['owner', 'admin', 'viewer'], 'workspace.finalize', ['finalize.read'])
            ->permission('workspace.finalize.confirm', ['owner', 'admin'], 'workspace.finalize', ['finalize.confirm'])
            ->permission('workspace.finalize.certify', ['owner', 'admin'], 'workspace.finalize', ['finalize.certify'])
            ->permission('workspace.finalize.unfinalize', ['owner'], 'workspace.finalize', ['finalize.unfinalize'])
            ->errors(self::CODES, [
                'finalize_stale' => 'This checklist changed since you opened it. Review it and try again.',
                'finalize_incomplete' => 'Every required step must be done before finalizing.',
                'finalize_finalized' => 'Finalized; un-finalize to change.',
            ]);
        if (in_array('mcp', $features, true)) {
            $registry->capabilityTools([ReadFinalizeGate::class, ConfirmFinalizeStep::class, FinalizeSubject::class, UnfinalizeSubject::class])
                ->mcpRefusalCodes(array_keys(self::CODES));
        }
    }

    public function register(): void
    {
        $this->app->singleton(Flows::class);
    }
}
