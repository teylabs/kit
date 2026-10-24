<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationOperation extends Data
{
    /** @param list<string> $operandOrder
     * @param list<string> $includedKeys */
    public function __construct(public string $name, public string $version, public array $operandOrder, public ?string $divisor, public array $includedKeys, public ?ExplanationFraction $exactFraction, public ?ExplanationClamp $bounds) {}
}
