<?php

namespace Tey\Kit\Features\Deliver;

use Illuminate\Mail\Events\MessageSent;

/** The provider's message id of each delivery the kit sent in this process, read from its X-Kit-Delivery header when the mailer reports it sent. */
final class SentMessages
{
    /** @var array<int, string> */
    private array $ids = [];

    public function record(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-Kit-Delivery');
        $id = $header === null ? null : (int) $header->getBodyAsString();
        $messageId = $event->sent->getMessageId();
        if ($id !== null && $id > 0 && $messageId !== '') {
            $this->ids[$id] = $messageId;
        }
    }

    public function pull(int $delivery): ?string
    {
        $id = $this->ids[$delivery] ?? null;
        unset($this->ids[$delivery]);

        return $id;
    }
}
