<?php

namespace Tey\Kit\Features\Finalize\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a finalize gate stands, computed on the server in the same pass as its steps. The certify step is not counted. `complete` is true only when every
 * required step is done. `revision` is a hash of every step's state and fingerprint: Finalize sends it back and is refused if it no longer matches.
 */
#[TypeScript]
final class GateProgress extends Data
{
    public function __construct(
        public int $total,
        public int $done,
        public int $required,
        public int $requiredDone,
        public bool $complete,
        public ?string $currentKey,
        public string $revision,
    ) {}
}
