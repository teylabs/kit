<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationAllocation extends Data
{
    public function __construct(public string $group, public string $policy, public string $exactShareMinor, public string $baseMinor, public string $remainderMinor, public string $allocatedMinor, public string $adjustmentMinor, public string $tieBreak) {}
}
