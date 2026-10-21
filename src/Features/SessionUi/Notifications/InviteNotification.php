<?php

namespace Tey\Kit\Features\SessionUi\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation email: "{Inviter} invited you to {Workspace}", the inviter's personal message quoted at the top when there is one, the facts, and one button. A specific
 * subject and a preheader, From the product's updates address and Reply-To its support address (the brand sets both for every mail), with a plain-text part too.
 */
final class InviteNotification extends Notification
{
    public function __construct(public string $inviter, public string $workspace, public string $product, public string $url, public CarbonInterface $expiresAt, public ?string $personal = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $facts = __('You have been invited to join :workspace on :product. The link works until :date.', ['workspace' => $this->workspace, 'product' => $this->product, 'date' => $this->expiresAt->toFormattedDayDateString()]);

        return (new MailMessage)
            ->subject(__(':inviter invited you to :workspace', ['inviter' => $this->inviter, 'workspace' => $this->workspace]))
            ->markdown('kit-mail::invitation', [
                'headline' => __(':inviter invited you to :workspace', ['inviter' => $this->inviter, 'workspace' => $this->workspace]),
                'preheader' => $this->personal !== null ? $this->personal : __('Join :workspace on :product. One click, no extra account details needed.', ['workspace' => $this->workspace, 'product' => $this->product]),
                'personal' => $this->personal,
                'inviter' => $this->inviter,
                'facts' => $facts,
                'url' => $this->url,
                'workspace' => $this->workspace,
            ]);
    }
}
