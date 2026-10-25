<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

enum SourceKind: string
{
    case Record = 'record';
    case Manual = 'manual';
    case Config = 'config';
}
