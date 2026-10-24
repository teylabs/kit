<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationExclusion extends Data
{
    public function __construct(public string $key, public string $label, public string $reason, public ?ExplanationSource $source) {}
}
