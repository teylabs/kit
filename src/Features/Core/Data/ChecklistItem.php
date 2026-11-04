<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a server-computed checklist (kit `Checklist`): the server decides every state, and the client only draws it.
 *
 * - `state` is pending, active (working on it now), done, failed or skipped.
 * - `detail` is a line under the label, such as what the step needs.
 * - `reason` says why it failed or was skipped.
 * - `required: false` marks an optional step, which doesn't hold back completion.
 * - `action` is an optional link or named action the row offers ("Add the photos", "Retry").
 */
#[TypeScript]
final class ChecklistItem extends Data
{
    public const array STATES = ['pending', 'active', 'done', 'failed', 'skipped'];

    public function __construct(
        public string $key,
        public string $label,
        #[LiteralTypeScriptType("'pending' | 'active' | 'done' | 'failed' | 'skipped'")] public string $state = 'pending',
        public ?string $detail = null,
        public ?string $reason = null,
        public bool $required = true,
        public ?ChecklistAction $action = null,
    ) {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("A checklist item's state is one of ".implode(', ', self::STATES).", not {$state}.");
        }
    }
}
