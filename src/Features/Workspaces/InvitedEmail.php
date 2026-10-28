<?php

namespace Tey\Kit\Features\Workspaces;

use Tey\Concierge\Support\InvitationRules;

/**
 * An invitation reaches its address by email, so opening or accepting it with the token proves that address. A signed-in account whose email is the invited
 * one is marked verified, instead of being sent to verify it again. Any other address is left alone (Concierge refuses it as the wrong account).
 */
final class InvitedEmail
{
    public static function verify(?object $user, ?string $invited): bool
    {
        if ($user === null || $invited === null || $invited === '' || $user->email_verified_at !== null) {
            return false;
        }
        if (! hash_equals(InvitationRules::normalizeEmail($invited), InvitationRules::normalizeEmail((string) $user->email))) {
            return false;
        }
        $user->forceFill(['email_verified_at' => now()])->save();

        return true;
    }
}
