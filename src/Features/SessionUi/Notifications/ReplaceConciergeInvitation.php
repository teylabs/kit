<?php

namespace Tey\Kit\Features\SessionUi\Notifications;

use Illuminate\Notifications\Events\NotificationSending;
use Tey\Concierge\Notifications\InvitationNotification;
use Tey\Concierge\Support\ConciergeModels;

/**
 * Concierge sends its own plain invitation email. This swaps it for the kit's branded one at the moment of sending: the same recipient, the same link, but with who
 * invited them and their personal message. Until Concierge offers a hook for its notification, this is the seam.
 */
final class ReplaceConciergeInvitation
{
    public function handle(NotificationSending $event): ?bool
    {
        if (! $event->notification instanceof InvitationNotification) {
            return null;
        }
        $email = $event->notifiable->routes['mail'] ?? null;
        $original = $event->notification;
        $token = basename((string) parse_url($original->url, PHP_URL_PATH));
        $invitation = ConciergeModels::invitation()::query()->with('inviter')->where('token_hash', ConciergeModels::invitation()::hashToken($token))->first();
        if ($email === null || $invitation === null) {
            return null;
        }
        $event->notifiable->notifyNow(new InviteNotification((string) ($invitation->inviter?->name ?? config('app.name')), $original->workspaceName, (string) config('app.name'), $original->url, $original->expiresAt, $original->message));

        return false;
    }
}
