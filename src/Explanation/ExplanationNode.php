<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationNode extends Data
{
    /** @param list<ExplanationInput> $inputs
     * @param list<ExplanationNode> $children
     * @param list<ExplanationExclusion> $exclusions */
    public function __construct(public string $schemaVersion, public string $id, public ExplanationKind $kind, public string $label, public ExplanationValue $value, public ?string $unrounded, public ?ExplanationRounding $rounding, public ?ExplanationRule $rule, public array $inputs, public array $children, public array $exclusions, public ?string $note, public ?ExplanationOperation $operation, public ?ExplanationAllocation $allocation) {}
}
