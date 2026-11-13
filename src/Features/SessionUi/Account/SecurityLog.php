<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Models\SecurityEvent;
use Tey\Kit\Features\SessionUi\Notifications\SecurityNotice;
use Tey\Kit\Features\SessionUi\UserState;

/**
 * A user's security history: sign-ins, failed sign-ins, password changes and two-factor changes, written by listeners on the framework's and Fortify's
 * events. It is the user's own: read only by that user, never shown in a workspace, and kept.
 */
final class SecurityLog
{
    /** Event type => the line the user reads. */
    public const array LABELS = [
        'sign_in' => 'Signed in',
        'failed_sign_in' => 'Failed sign-in attempt',
        'password_changed' => 'Password changed',
        'password_set' => 'Password set',
        'email_changed' => 'Email address changed',
        'two_factor_enabled' => 'Two-factor authentication turned on (waiting for a code)',
        'two_factor_confirmed' => 'Two-factor authentication confirmed',
        'two_factor_disabled' => 'Two-factor authentication turned off',
        'two_factor_failed' => 'Failed two-factor code',
        'recovery_codes_regenerated' => 'Recovery codes regenerated',
        'sessions_ended' => 'Other sessions signed out',
        'passkey_added' => 'Passkey added',
        'passkey_removed' => 'Passkey removed',
    ];

    public static function record(int|string|null $userId, string $type, ?Request $request = null): void
    {
        if ($userId === null || ! array_key_exists($type, self::LABELS)) {
            return;
        }
        $request ??= app()->bound('request') ? request() : null;
        try {
            SecurityEvent::query()->create(['user_id' => $userId, 'type' => $type, 'ip' => $request?->ip(), 'agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 255)]);
        } catch (\Throwable $e) {
            // A history that cannot be written (the migration has not run yet) is reported, and never stops someone signing in. The report is
            // scrubbed: the query exception carries the bindings (the IP and user agent), so only its class and the event type go out.
            report(new \RuntimeException('The security log could not record "'.$type.'" ('.$e::class.').'));
        }
        // The change is also mailed to the account owner (SecurityNotice); an email change is mailed to the old address by UpdateProfile instead.
        if ($type !== 'email_changed' && array_key_exists($type, SecurityNotice::MESSAGES)) {
            $user = Users::model()::query()->find($userId);
            if ($user !== null) {
                self::notify($user, $type, $request);
            }
        }
    }

    /**
     * Mails the owner a SecurityNotice, unless `kit.security_mail` is off. To an address other than the account's (the old one, after an email
     * change) when `$to` is given. A mail that cannot be sent is reported and never stops the change it describes.
     */
    public static function notify(object $user, string $type, ?Request $request = null, ?string $to = null, ?string $newEmail = null): void
    {
        if (! config('kit.security_mail', true) || ! array_key_exists($type, SecurityNotice::MESSAGES)) {
            return;
        }
        $request ??= app()->bound('request') ? request() : null;
        $notice = new SecurityNotice($type, now()->toDayDateTimeString().' UTC', $request?->ip(), $request === null ? null : mb_substr((string) $request->userAgent(), 0, 255), $newEmail);
        try {
            if ($to !== null) {
                Notification::route('mail', $to)->notify($notice);
            } elseif (method_exists($user, 'notify') && filled($user->email ?? null)) {
                $user->notify($notice);
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('The security notice "'.$type.'" could not be sent ('.$e::class.').'));
        }
    }

    /** Listens for the events that make up the log. */
    public static function listen(Dispatcher $events): void
    {
        $events->listen('eloquent.deleted: '.Users::model(), fn ($user) => self::forget($user));
        $events->listen('eloquent.forceDeleted: '.Users::model(), fn ($user) => self::forget($user));
        $events->listen(Login::class, fn (Login $e) => self::record($e->user->getAuthIdentifier(), 'sign_in'));
        $events->listen(Failed::class, function (Failed $e): void {
            // A failed attempt is the account owner's business only when it names an account that exists.
            $user = $e->user ?? (isset($e->credentials['email']) ? Users::model()::query()->where('email', $e->credentials['email'])->first() : null);
            self::record($user?->getAuthIdentifier(), 'failed_sign_in');
        });
        // An account's first password (it had none: null or empty) is set, not changed: logged as password_set, which mails nobody. A brand-new owner or an
        // invitee choosing a password through the reset link would otherwise be told "your password was changed".
        $events->listen('eloquent.updating: '.Users::model(), function ($user): void {
            if ($user->isDirty('password') && trim((string) $user->getOriginal('password')) === '') {
                self::$firstPassword ??= new \WeakMap;
                self::$firstPassword[$user] = true;
            }
        });
        $events->listen(PasswordReset::class, fn (PasswordReset $e) => self::record($e->user->getAuthIdentifier(), self::passwordEvent($e->user)));
        $events->listen(PasswordUpdatedViaController::class, fn (PasswordUpdatedViaController $e) => self::record($e->user->getAuthIdentifier(), self::passwordEvent($e->user)));
        $events->listen(TwoFactorAuthenticationEnabled::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_enabled'));
        $events->listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_confirmed'));
        $events->listen(TwoFactorAuthenticationDisabled::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_disabled'));
        $events->listen(TwoFactorAuthenticationFailed::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_failed'));
        $events->listen(RecoveryCodesGenerated::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'recovery_codes_regenerated'));
        // Passkeys' events, by name: laravel/passkeys is optional.
        $events->listen('Laravel\\Passkeys\\Events\\PasskeyRegistered', fn ($e) => self::record($e->user->getAuthIdentifier(), 'passkey_added'));
        $events->listen('Laravel\\Passkeys\\Events\\PasskeyDeleted', fn ($e) => self::record($e->user->getAuthIdentifier(), 'passkey_removed'));
    }

    /**
     * @var ?\WeakMap<object, true> user models whose password was just set for the first time, until their password event is recorded. Keyed by the model
     * instance the password was saved on, which the password event carries, so the flag goes with that model: a first password set without the event never
     * reaches a later request's model of the same user in a long-lived worker (Octane). Without the flag, the change is logged and mailed as a change.
     */
    private static ?\WeakMap $firstPassword = null;

    private const string AWAITING_FIRST_PASSWORD = 'security.awaiting_first_password';

    /**
     * Marks an account created without a password the person chose (an owner provisioned with a random password, then sent a reset link): their first
     * password, however it is set (reset link or profile), is logged as password_set and mails nobody. Without the mark, an account whose password was
     * null or empty is treated the same way. The mark is cleared by that first set.
     */
    public static function awaitFirstPassword(object $user): void
    {
        UserState::set((int) $user->getAuthIdentifier(), self::AWAITING_FIRST_PASSWORD);
    }

    /** password_set for an account's first password (awaitFirstPassword(), or none before), password_changed otherwise. */
    private static function passwordEvent(object $user): string
    {
        $id = (string) $user->getAuthIdentifier();
        $marked = UserState::has((int) $id, self::AWAITING_FIRST_PASSWORD);
        if ($marked || isset(self::$firstPassword[$user])) {
            unset(self::$firstPassword[$user]);
            if ($marked) {
                UserState::forget((int) $id, self::AWAITING_FIRST_PASSWORD);
            }

            return 'password_set';
        }

        return 'password_changed';
    }

    /**
     * When the user goes, their log and their kit state go: a delete without soft deletes, or a force delete. A soft delete keeps the rows until the force delete. The foreign key
     * (`on delete cascade`) does the same in the database; this covers apps where it cannot apply.
     */
    public static function forget(object $user): void
    {
        if (method_exists($user, 'isForceDeleting') && ! $user->isForceDeleting()) {
            return;
        }
        SecurityEvent::query()->where('user_id', $user->getKey())->delete();
        // What the kit remembers about the person (timezone, welcome steps, dismissed cards) goes with them, by the same rule.
        UserState::forgetAll((int) $user->getKey());
    }

    /** A short, human reading of a user-agent string: "Chrome on macOS". */
    public static function describeAgent(?string $agent): string
    {
        if ($agent === null || trim($agent) === '') {
            return 'Unknown device';
        }
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $system = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system === null ? $browser : $browser.' on '.$system;
    }
}
