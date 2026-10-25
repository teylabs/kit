<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationClamp extends Data
{
    public function __construct(public string $lower, public string $upper, public string $selected) {}
}
