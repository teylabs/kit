<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationInput extends Data
{
    public function __construct(public string $key, public string $label, public ExplanationValue $value, public string $exact, public ExplanationSource $source) {}
}
