<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationValue extends Data
{
    public function __construct(public string $display, public string $minor, public int $scale, public ?string $currency, public ?string $unit) {}
}
