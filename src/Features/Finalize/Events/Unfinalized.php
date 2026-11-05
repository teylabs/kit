<?php

namespace Tey\Kit\Features\Finalize\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Finalize\Models\Finalization;

/** Dispatched after the transaction commits, so a listener's mail or PDF never describes a change that rolled back. */
final class Unfinalized
{
    public function __construct(public readonly string $flow, public readonly Model $subject, public readonly Finalization $finalization) {}
}
