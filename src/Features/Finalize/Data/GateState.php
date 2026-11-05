<?php

namespace Tey\Kit\Features\Finalize\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/**
 * A finalize gate for one subject, as a page or an MCP tool receives it: the steps and progress, the statement the certifier agrees to, the Finalize action
 * (null when the viewer may not finalize; disabled with a reason until the gate is complete) and the current finalization, if any.
 */
#[TypeScript]
final class GateState extends Data
{
    /** @param  list<GateStep>  $steps */
    public function __construct(
        public string $flow,
        public string $subject,
        #[DataCollectionOf(GateStep::class)] public array $steps,
        public GateProgress $progress,
        public string $statement,
        public string $statementVersion,
        public ?Action $finalize,
        public ?FinalizationRecord $finalization,
    ) {}
}
