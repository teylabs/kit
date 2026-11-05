<?php

namespace Tey\Kit\Features\Finalize\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/**
 * One step of a finalize gate, as the server computed it. A superset of the kit's ChecklistItem, so the kit `Checklist` draws it: `kind` is check,
 * confirmation or certify; `errors` are the reasons it does not hold, each with an optional field and fix link; `actions` are its fix as descriptors
 * (Confirm, a link, an anchor), already gated; `confirmation` is the latest one, if any.
 */
#[TypeScript]
final class GateStep extends Data
{
    /**
     * @param  list<GateError>  $errors
     * @param  list<Action>  $actions
     */
    public function __construct(
        public string $key,
        public string $label,
        #[LiteralTypeScriptType("'check' | 'confirmation' | 'certify'")] public string $kind,
        #[LiteralTypeScriptType("'pending' | 'active' | 'done' | 'skipped'")] public string $state,
        public bool $required,
        public ?string $detail,
        public ?string $reason,
        #[DataCollectionOf(GateError::class)] public array $errors,
        #[DataCollectionOf(Action::class)] public array $actions,
        public ?GateConfirmation $confirmation,
    ) {}
}
