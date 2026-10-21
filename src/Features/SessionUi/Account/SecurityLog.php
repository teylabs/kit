<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Models\SecurityEvent;

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
        'email_changed' => 'Email address changed',
        'two_factor_enabled' => 'Two-factor authentication turned on (waiting for a code)',
        'two_factor_confirmed' => 'Two-factor authentication confirmed',
        'two_factor_disabled' => 'Two-factor authentication turned off',
        'two_factor_failed' => 'Failed two-factor code',
        'recovery_codes_regenerated' => 'Recovery codes regenerated',
        'sessions_ended' => 'Other sessions signed out',
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
        $events->listen(PasswordReset::class, fn (PasswordReset $e) => self::record($e->user->getAuthIdentifier(), 'password_changed'));
        $events->listen(PasswordUpdatedViaController::class, fn (PasswordUpdatedViaController $e) => self::record($e->user->getAuthIdentifier(), 'password_changed'));
        $events->listen(TwoFactorAuthenticationEnabled::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_enabled'));
        $events->listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_confirmed'));
        $events->listen(TwoFactorAuthenticationDisabled::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_disabled'));
        $events->listen(TwoFactorAuthenticationFailed::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'two_factor_failed'));
        $events->listen(RecoveryCodesGenerated::class, fn ($e) => self::record($e->user->getAuthIdentifier(), 'recovery_codes_regenerated'));
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
        \Tey\Kit\Features\SessionUi\UserState::forgetAll((int) $user->getKey());
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
