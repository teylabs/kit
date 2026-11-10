<?php

namespace Tey\Kit\Features\Deliver\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/**
 * One delivery in a subject's history. `stillFinal` is false when the finalization it sent has since been undone, or its `finalization_ref` no longer
 * matches the current one ("sent version is no longer final"), and null when it was sent without either. `actions` are Cancel, Resend and Revoke link, already gated.
 */
#[TypeScript]
final class DeliveryView extends Data
{
    /**
     * @param  list<DeliveryRecipientView>  $recipients
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions
     */
    public function __construct(
        public int $id,
        #[LiteralTypeScriptType("'scheduled' | 'sending' | 'sent' | 'failed' | 'unknown' | 'cancelled'")] public string $state,
        public bool $test,
        public string $by,
        public string $channel,
        public string $createdAt,
        public ?string $sendAt,
        public ?string $sentAt,
        public string $subject,
        #[LiteralTypeScriptType("'attached' | 'linked' | 'none'")] public string $attachment,
        #[DataCollectionOf(DeliveryRecipientView::class)] public array $recipients,
        public ?bool $stillFinal,
        public bool $linkActive,
        public ?string $firstOpenedAt,
        public ?string $note,
        public ?int $resentFromId,
        #[DataCollectionOf(Action::class)] public array $actions,
    ) {}
}
