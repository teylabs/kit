<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationBoundaries extends Data
{
    public function __construct(public ?string $lower, public ?string $upper, public bool $lowerInclusive, public bool $upperInclusive, public string $unit) {}
}
