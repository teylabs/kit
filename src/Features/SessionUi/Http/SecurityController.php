<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\OtherSessions;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Models\SecurityEvent;
use Tey\Kit\Features\SessionUi\PasskeyOffer;
use Tey\Kit\Features\SessionUi\ViewModels\SecurityViewModel;

/** /me/security: two-factor authentication (Fortify's actions), the devices signed in, and your own security log. The log is yours alone. */
class SecurityController
{
    public function show(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);

        $events = SecurityEvent::query()->where('user_id', $user->getKey())->latest('id')->limit(50)->get()
            ->map(fn (SecurityEvent $event): array => ['type' => $event->type, 'label' => SecurityLog::LABELS[$event->type] ?? $event->type, 'ip' => $event->ip, 'agent' => SecurityLog::describeAgent($event->agent), 'at' => $event->created_at->toIso8601String()])->all();
        $model = new SecurityViewModel(
            'Security',
            $this->twoFactor($request, $user),
            ['supported' => OtherSessions::supported(), 'items' => OtherSessions::list($user, $request->session()->getId())],
            $events,
            '/me/security/two-factor',
            '/me/security/recovery-codes',
            '/me/security/sessions',
            $request->session()->get('account_notice'),
            $this->passkeys($user),
        );

        return $request->expectsJson() && ! $request->header('X-Inertia') ? response()->json($model) : Inertia::render('Workspace/Account/Security/Page', $model);
    }

    /** @return ?array{options_url: string, store_url: string, confirm_url: string, url: string, items: list<array{id: int, name: string, created: string, last_used: ?string}>} */
    private function passkeys(object $user): ?array
    {
        if (! PasskeyOffer::available($user) || ! method_exists($user, 'passkeys')) {
            return null;
        }
        $items = $user->passkeys()->orderBy('id')->get()->map(fn ($passkey): array => [
            'id' => (int) $passkey->getKey(),
            'name' => (string) $passkey->name,
            'created' => $passkey->created_at?->toIso8601String() ?? '',
            'last_used' => $passkey->last_used_at?->toIso8601String(),
        ])->values()->all();

        // confirm_url: Fortify's password confirmation, which its passkey registration requires (password.confirm) unless the host turns that off.
        return ['options_url' => url('/user/passkeys/options'), 'store_url' => url('/user/passkeys'), 'confirm_url' => url('/user/confirm-password'), 'url' => url('/me/security/passkeys'), 'items' => $items];
    }

    /** @return array{state: string, qr_svg: ?string, secret_key: ?string, codes_left: int, recovery_codes: list<string>} */
    private function twoFactor(Request $request, object $user): array
    {
        if (! method_exists($user, 'twoFactorQrCodeSvg')) {
            return ['state' => 'unavailable', 'qr_svg' => null, 'secret_key' => null, 'codes_left' => 0, 'recovery_codes' => []];
        }
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
        $on = $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;

        return [
            'state' => $on ? 'on' : ($pending ? 'pending' : 'off'),
            'qr_svg' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'secret_key' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'codes_left' => $on ? count($user->recoveryCodes()) : 0,
            // Recovery codes are shown once, right after they are made.
            'recovery_codes' => array_values((array) $request->session()->get('recovery_codes', [])),
        ];
    }
}
