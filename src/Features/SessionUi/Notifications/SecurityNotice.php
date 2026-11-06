<?php

namespace Tey\Kit\Features\SessionUi\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\MailUrl;

/**
 * "This changed on your account" mail: a password changed, an email address changed (sent to the old address), two-factor turned on or off,
 * recovery codes regenerated, a passkey added or removed. Security mail: no manage-notifications link, and it can't be turned off per person
 * (an app turns all of it off with `kit.security_mail`). It says when and from where, and what to do if it wasn't them.
 */
final class SecurityNotice extends Notification
{
    /** Event type => [subject, sentence]. `:product` is the product's name. */
    public const array MESSAGES = [
        'password_changed' => ['Your :product password was changed', 'The password for your :product account was changed.'],
        'email_changed' => ['The email address on your :product account was changed', 'The email address on your :product account was changed from this address to :new. Mail about the account now goes there.'],
        'two_factor_confirmed' => ['Two-factor authentication is on for your :product account', 'Two-factor authentication was turned on for your :product account.'],
        'two_factor_disabled' => ['Two-factor authentication was turned off on your :product account', 'Two-factor authentication was turned off for your :product account. Signing in now needs only your password.'],
        'recovery_codes_regenerated' => ['New recovery codes for your :product account', 'New two-factor recovery codes were made for your :product account. The old ones no longer work.'],
        'passkey_added' => ['A passkey was added to your :product account', 'A passkey was added to your :product account.'],
        'passkey_removed' => ['A passkey was removed from your :product account', 'A passkey was removed from your :product account.'],
    ];

    public function __construct(public readonly string $type, public readonly string $when, public readonly ?string $ip = null, public readonly ?string $agent = null, public readonly ?string $newEmail = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): FluentMailMessage
    {
        $product = (string) config('app.name');
        [$subject, $sentence] = self::MESSAGES[$this->type];
        $mail = (new FluentMailMessage)
            ->security()
            ->subject(__($subject, ['product' => $product]))
            ->paragraph(__($sentence, ['product' => $product, 'new' => (string) $this->newEmail]))
            ->facts([__('When') => $this->when, __('IP address') => (string) $this->ip, __('Browser') => (string) $this->agent]);
        $mail->paragraph(__('If this was you, there is nothing to do. If it wasn\'t, reset your password now and check your account\'s security settings.'));
        if (Route::has('password.request')) {
            $mail->button(__('Reset password'), MailUrl::to(route('password.request', [], false)));
        }

        return $mail;
    }
}
