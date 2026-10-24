<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

enum RoundingMode: string
{
    case HalfUp = 'half-up';
    case HalfEven = 'half-even';
    case TowardZero = 'toward-zero';
    case Floor = 'floor';
    case Ceiling = 'ceiling';
}
