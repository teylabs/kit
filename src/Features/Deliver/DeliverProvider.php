<?php

namespace Tey\Kit\Features\Deliver;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Deliver\Console\SweepDeliveriesCommand;
use Tey\Kit\Features\Deliver\Integrations\Mcp\CancelDelivery;
use Tey\Kit\Features\Deliver\Integrations\Mcp\PreviewDelivery;
use Tey\Kit\Features\Deliver\Integrations\Mcp\ReadDeliveries;
use Tey\Kit\Features\Deliver\Integrations\Mcp\ResendDelivery;
use Tey\Kit\Features\Deliver\Integrations\Mcp\RevokeDeliveryLink;
use Tey\Kit\Features\Deliver\Integrations\Mcp\SendDelivery;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;
use Tey\Kit\Features\Finalize\Events\Unfinalized;

/**
 * Deliver to customer: email a finalized subject (a report, an invoice) with a preview of the exact email, send now or at a time, a recorded history with
 * each address's delivery status, resend, and a signed customer link that can be revoked. The app registers its definitions with `Deliveries`. Three
 * abilities on one capability: `deliver.read`, `deliver.send` (send, schedule, cancel) and `deliver.resend` (resend, revoke a link). Opt-in; it needs the
 * app's scheduler running every minute for scheduled sends.
 */
final class DeliverProvider extends ServiceProvider implements Contributes
{
    public const array CODES = ['deliver_definition_unknown' => 404, 'deliver_subject_not_found' => 404, 'deliver_not_found' => 404, 'deliver_no_attachment' => 404,
        'deliver_stale' => 409, 'deliver_gated' => 409, 'deliver_not_cancellable' => 409, 'deliver_not_resendable' => 409, 'deliver_link_inactive' => 409,
        'deliver_claim_key_reused' => 409, 'deliver_claim_key_required' => 422, 'deliver_recipients_required' => 422, 'deliver_recipient_unknown' => 422,
        'deliver_address_invalid' => 422, 'deliver_subject_required' => 422, 'deliver_send_at_invalid' => 422];

    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/deliver.php', 'web', $routes)
            ->routes(__DIR__.'/routes/deliver-guest.php', 'web', $routes)
            ->scopes(['deliver.read' => 'Read deliveries and preview them', 'deliver.send' => 'Send, schedule and cancel deliveries', 'deliver.resend' => 'Resend deliveries and revoke their links'])
            ->abilities(['deliver.read' => 'workspace.deliver', 'deliver.send' => 'workspace.deliver', 'deliver.resend' => 'workspace.deliver'])
            ->capabilities(['workspace.deliver'])
            ->permission('workspace.deliver.view', ['owner', 'admin', 'viewer'], 'workspace.deliver', ['deliver.read'])
            ->permission('workspace.deliver.send', ['owner', 'admin'], 'workspace.deliver', ['deliver.send'])
            ->permission('workspace.deliver.resend', ['owner', 'admin'], 'workspace.deliver', ['deliver.resend'])
            ->errors(self::CODES, [
                'deliver_stale' => 'This changed since you opened it. Review it and try again.',
                'deliver_gated' => 'This cannot be sent yet.',
                'deliver_recipient_unknown' => 'A recipient is no longer one of this record\'s contacts.',
            ]);
        if (in_array('mcp', $features, true)) {
            $registry->capabilityTools([ReadDeliveries::class, PreviewDelivery::class, SendDelivery::class, ResendDelivery::class, CancelDelivery::class, RevokeDeliveryLink::class])
                ->mcpRefusalCodes(array_keys(self::CODES));
        }
    }

    public function register(): void
    {
        $this->app->singleton(Deliveries::class);
        $this->app->singleton(SentMessages::class);
    }

    public function boot(): void
    {
        Event::listen(MessageSent::class, fn (MessageSent $event) => $this->app->make(SentMessages::class)->record($event));
        // Un-finalizing a subject cancels its scheduled sends; what was already delivered stays as it is.
        Event::listen(Unfinalized::class, fn (Unfinalized $event) => $this->app->make(DeliveryDesk::class)->cancelForUnfinalized($event->flow, $event->subject));
        if ($this->app->runningInConsole()) {
            $this->commands([SweepDeliveriesCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('kit:deliver-sweep')->everyMinute()->withoutOverlapping()->onOneServer());
    }
}
