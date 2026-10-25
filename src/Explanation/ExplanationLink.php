<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationLink extends Data
{
    public function __construct(public string $label, public string $url) {}
}
