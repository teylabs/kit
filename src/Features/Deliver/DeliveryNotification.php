<?php

namespace Tey\Kit\Features\Deliver;

use Tey\Kit\Mail\FluentMailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * The app's Notification as the kit sends it: its own `toMail()`, then Cc, Bcc, the attachment, the test marker and a header naming the delivery, so the
 * provider's message id is recorded against it. The preview renders this same wrapper.
 */
final class DeliveryNotification extends Notification
{
    /**
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     */
    public function __construct(
        private readonly Notification $inner,
        private readonly array $cc,
        private readonly array $bcc,
        private readonly ?DeliveryAttachment $attach,
        private readonly bool $test,
        private readonly ?int $deliveryId,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): FluentMailMessage
    {
        /** @var FluentMailMessage $mail */
        $mail = $this->inner->toMail($notifiable);
        if (! $mail instanceof FluentMailMessage) {
            throw new \RuntimeException('A delivery notification\'s toMail() returns a FluentMailMessage.');
        }
        if ($this->cc !== []) {
            $mail->cc($this->cc);
        }
        if ($this->bcc !== []) {
            $mail->bcc($this->bcc);
        }
        if ($this->attach !== null) {
            $mail->attachData($this->attach->contents, $this->attach->filename, ['mime' => $this->attach->mime]);
        }
        if ($this->test) {
            $mail->subject('[Test] '.($mail->subject ?? ''));
        }
        if ($this->deliveryId !== null) {
            $id = $this->deliveryId;
            $mail->withSymfonyMessage(fn (Email $message) => $message->getHeaders()->addTextHeader('X-Kit-Delivery', (string) $id));
        }

        return $mail;
    }
}
