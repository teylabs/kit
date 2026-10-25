<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

enum ExplanationKind: string
{
    case Sum = 'sum';
    case Difference = 'difference';
    case Product = 'product';
    case Mean = 'mean';
    case Percent = 'percent';
    case Min = 'min';
    case Max = 'max';
    case Clamp = 'clamp';
    case Lookup = 'lookup';
    case Rounding = 'rounding';
    case Input = 'input';
    case Custom = 'custom';
}
