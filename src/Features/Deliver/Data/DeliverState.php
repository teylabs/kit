<?php

namespace Tey\Kit\Features\Deliver\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/**
 * A subject's deliver page as the server computed it: whether it may be sent (and why not), the editable defaults, the people it may go to, the
 * internal copies, its history, and the actions. Every write carries `revision`; a change since it was read is refused with `deliver_stale` (409).
 */
#[TypeScript]
final class DeliverState extends Data
{
    /**
     * @param  list<RecipientOption>  $recipientOptions
     * @param  list<string>  $internalCopies
     * @param  list<DeliveryView>  $deliveries
     */
    public function __construct(
        public string $definition,
        public string $subject,
        public bool $sendable,
        public ?string $reason,
        public string $defaultSubject,
        public string $defaultMessage,
        #[DataCollectionOf(RecipientOption::class)] public array $recipientOptions,
        public array $internalCopies,
        #[DataCollectionOf(DeliveryView::class)] public array $deliveries,
        public string $revision,
        public ?Action $preview,
        public ?Action $send,
        public ?Action $testSend,
        public ?string $attachmentUrl,
    ) {}
}
