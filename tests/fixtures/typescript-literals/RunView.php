<?php

namespace Tey\Kit\Fixtures\TypeScriptLiterals;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RunView extends Data
{
    /**
     * @param  'queued'|'running'|'done'  $status
     * @param  1|2|3|null  $priority
     */
    public function __construct(public string $status, public ?int $priority) {}
}
