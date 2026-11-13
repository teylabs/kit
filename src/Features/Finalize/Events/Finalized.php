<?php

namespace Tey\Kit\Features\Finalize\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Finalize\Contracts\Finalization;

/** Dispatched after the transaction commits, so a listener's mail or PDF never describes a change that rolled back. `extras` holds the flow's validated declared extras (`Flow::extras()`), empty without them. */
final class Finalized
{
    /** @param  array<string, mixed>  $extras */
    public function __construct(public readonly string $flow, public readonly Model $subject, public readonly Model&Finalization $finalization, public readonly array $extras = []) {}
}
