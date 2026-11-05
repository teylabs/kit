<?php

namespace Tey\Kit\Features\Finalize\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** A subject's current finalization, for the kit `FinalizedBanner`: who and when, through which channel, and the Un-finalize action when the viewer may use it. */
#[TypeScript]
final class FinalizationRecord extends Data
{
    public function __construct(
        public string $by,
        public string $at,
        public string $channel,
        public string $statementVersion,
        public ?Action $unfinalize,
    ) {}
}
