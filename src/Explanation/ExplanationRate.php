<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationRate extends Data
{
    public function __construct(public string $exact, public string $display, public string $unit) {}
}
