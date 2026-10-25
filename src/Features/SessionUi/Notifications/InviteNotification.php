<?php

namespace Tey\Kit\Features\SessionUi\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;
use Tey\Kit\Mail\FluentMailMessage;

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

    public function toMail(object $notifiable): FluentMailMessage
    {
        $headline = __(':inviter invited you to :workspace', ['inviter' => $this->inviter, 'workspace' => $this->workspace]);

        return (new FluentMailMessage)
            ->subject($headline)
            ->preheader($this->personal ?? __('Join :workspace on :product. One click, no extra account details needed.', ['workspace' => $this->workspace, 'product' => $this->product]))
            ->headline($headline)
            ->when($this->personal !== null, fn (FluentMailMessage $mail) => $mail->quote($this->personal."\n".__('— :name', ['name' => $this->inviter])))
            ->paragraph(__('You have been invited to join :workspace on :product. The link works until :date.', ['workspace' => $this->workspace, 'product' => $this->product, 'date' => $this->expiresAt->toFormattedDayDateString()]))
            ->button(__('Join :workspace', ['workspace' => $this->workspace]), $this->url)
            ->paragraph(__('If the button does not work, open this link: :url', ['url' => $this->url]));
    }
}
