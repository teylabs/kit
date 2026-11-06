<?php

namespace Tey\Kit\Features\Deliver\Console;

use Illuminate\Console\Command;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** The scheduler's deliver pass (every minute, registered by the kit): due scheduled sends, and rows stuck mid-send. */
final class SweepDeliveriesCommand extends Command
{
    protected $signature = 'kit:deliver-sweep';

    protected $description = 'Send or cancel due scheduled deliveries, and mark stuck sends as unknown';

    public function handle(DeliveryDesk $desk): int
    {
        $counts = $desk->sweep();
        $this->line("Sent {$counts['sent']}, cancelled {$counts['cancelled']}, unknown {$counts['unknown']}.");

        return self::SUCCESS;
    }
}
