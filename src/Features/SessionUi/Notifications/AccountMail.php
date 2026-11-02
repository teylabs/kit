<?php

namespace Tey\Kit\Features\SessionUi\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\MailUrl;

/**
 * The account mail Fortify sends, in the kit's words instead of Laravel's stock "Hello!": the password reset link and the email address check. Both are security mail, so
 * neither has a manage-notifications link, and their buttons are in sentence case. The reset link is built on `app.url` (MailUrl), so it is https on an https app
 * whatever the request or worker saw; a host's own ResetPassword::createUrlUsing() still decides it. A host's own toMailUsing() callbacks are kept.
 */
final class AccountMail
{
    public static function register(): void
    {
        ResetPassword::$toMailCallback ??= fn (object $notifiable, string $token): FluentMailMessage => self::resetPassword($notifiable, $token);
        VerifyEmail::$toMailCallback ??= fn (object $notifiable, string $url): FluentMailMessage => self::verifyEmail($url);
    }

    public static function resetPassword(object $notifiable, string $token): FluentMailMessage
    {
        $url = ResetPassword::$createUrlCallback !== null
            ? (string) call_user_func(ResetPassword::$createUrlCallback, $notifiable, $token)
            : MailUrl::to(route('password.reset', ['token' => $token, 'email' => method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : null], false));
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $product = (string) config('app.name');

        return (new FluentMailMessage)
            ->security()
            ->subject(__('Reset your :product password', ['product' => $product]))
            ->preheader(__('The link works for :count minutes.', ['count' => $minutes]))
            ->headline(__('Reset your password'))
            ->paragraph(__('Someone asked to reset the password for your :product account. Choose a new one with the button below. The link works for :count minutes.', ['product' => $product, 'count' => $minutes]))
            ->button(__('Reset password'), $url)
            ->paragraph(__('If you did not ask for this, you can ignore this email. Your password stays as it is.'))
            ->paragraph(__('If the button does not work, open this link: :url', ['url' => $url]));
    }

    public static function verifyEmail(string $url): FluentMailMessage
    {
        $product = (string) config('app.name');

        return (new FluentMailMessage)
            ->security()
            ->subject(__('Verify your email address'))
            ->preheader(__('One click confirms this address for :product.', ['product' => $product]))
            ->headline(__('Verify your email address'))
            ->paragraph(__('Confirm that this is your email address for :product with the button below.', ['product' => $product]))
            ->button(__('Verify email address'), $url)
            ->paragraph(__('If you did not create an account, you can ignore this email.'))
            ->paragraph(__('If the button does not work, open this link: :url', ['url' => $url]));
    }
}
