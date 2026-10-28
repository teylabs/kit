<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Validation\Rules\Password;

/**
 * The kit's password policy, NIST 800-63B style: at least 8 characters and not found in a known data breach, with no composition rules (no mixed case,
 * numbers or symbols). Set as `Password::defaults()` when session-ui boots; a host that calls `Password::defaults()` in its own provider (which boots after the
 * kit's) replaces it. Every kit password field validates with `Password::defaults()`, says the breach failure in plain words, and shows the same rules
 * under the field (`kitPassword`, shared with every page).
 */
final class PasswordPolicy
{
    public static function default(): Password
    {
        return Password::min(8)->uncompromised();
    }

    /** Custom messages for a validator with a `password` field: the breach failure in plain words, from the kit's lang (`kit::validation.password.uncompromised`). */
    public static function messages(string $attribute = 'password'): array
    {
        $message = trans('kit::validation.password.uncompromised');

        // Before the kit's lang is loaded (KitServiceProvider registers it), the same words.
        return [$attribute.'.password.uncompromised' => is_string($message) && $message !== 'kit::validation.password.uncompromised' ? $message : 'This password has appeared in a data breach, so it isn\'t safe. Pick another.'];
    }

    /** What the page shows under a new-password field, from the rule in force (the host's, when it set its own). */
    public static function client(): array
    {
        $applied = Password::defaults()->appliedRules();

        return ['min' => (int) ($applied['min'] ?? 8), 'uncompromised' => (bool) ($applied['uncompromised'] ?? false)];
    }
}
