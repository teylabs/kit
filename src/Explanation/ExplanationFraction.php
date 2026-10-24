<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationFraction extends Data
{
    public function __construct(public string $numerator, public string $denominator) {}
}
