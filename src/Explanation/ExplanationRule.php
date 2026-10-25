<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationRule extends Data
{
    public function __construct(public string $key, public string $version, public string $label, public ?string $tier, public ?ExplanationBoundaries $boundaries, public ?ExplanationRate $rate, public ?string $orientation, public ?string $selection) {}
}
