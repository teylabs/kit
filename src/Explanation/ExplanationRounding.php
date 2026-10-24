<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationRounding extends Data
{
    public function __construct(public string $policy, public RoundingMode $mode, public int $place, public string $before, public string $after) {}
}
