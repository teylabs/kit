<?php

namespace Tey\Kit\Features\Settings\Setup;

use Illuminate\Notifications\Notification;
use Tey\Kit\Mail\FluentMailMessage;

/** Settings → Setup's test mail, sent now (never queued) to the viewer who asked for it. */
final class SetupTestMail extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): FluentMailMessage
    {
        return (new FluentMailMessage)->subject('Test mail from '.config('app.name'))->paragraph('This is a test from '.config('app.name').'\'s setup page. If it arrived, mail works.');
    }
}
