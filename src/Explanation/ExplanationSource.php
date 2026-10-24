<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationSource extends Data
{
    public function __construct(public SourceKind $kind, public string $key, public string $label, public ?ExplanationLink $link, public ?string $reason, public ?string $author, public ?string $version) {}
}
