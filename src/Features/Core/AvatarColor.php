<?php

namespace Tey\Kit\Features\Core;

/**
 * One colour per person: the disc behind their initials, the same in every place that draws them (the shell, notes, comments, people lists and an activity
 * feed). A stored `#rrggbb` wins; otherwise the colour is derived from a stable identity (the user id), so it never needs storing and never drifts. Every
 * palette colour carries white initials at 4.5:1 or better and stands at 3:1 or better against both the light and the dark page. Mirrors `avatarColor()` in
 * the kit's JavaScript (Kit/Layout/avatarColor.ts), so a colour derived on either side is the same.
 */
final class AvatarColor
{
    /** sky, violet, pink, amber, emerald, red, indigo and teal, each dark enough for white initials */
    public const PALETTE = ['#0369a1', '#7c3aed', '#be185d', '#b45309', '#047857', '#b91c1c', '#4f46e5', '#0f766e'];

    /** The colour for an identity (a user id, or any stable key); a valid stored `#rrggbb` is returned as is. */
    public static function for(int|string $identity, ?string $stored = null): string
    {
        if ($stored !== null && preg_match('/^#[0-9a-f]{6}$/i', $stored) === 1) {
            return strtolower($stored);
        }
        // 31-hash over code points, wrapped to a signed 32-bit integer as JavaScript's Math.imul does.
        $hash = 0;
        foreach (mb_str_split((string) $identity) as $character) {
            $hash = ($hash * 31 + mb_ord($character)) & 0xFFFFFFFF;
        }
        $hash = $hash > 0x7FFFFFFF ? $hash - 0x100000000 : $hash;

        return self::PALETTE[abs($hash) % count(self::PALETTE)];
    }

    /** A user's colour: their `avatar_color` attribute when they store one, else derived from their key. */
    public static function of(object $user): string
    {
        $stored = $user->avatar_color ?? null;

        return self::for(method_exists($user, 'getKey') ? $user->getKey() : $user->id, is_string($stored) ? $stored : null);
    }
}
