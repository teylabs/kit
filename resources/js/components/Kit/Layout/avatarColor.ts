// One colour per person (docs/avatars.md). Mirrors Tey\Kit\Features\Core\AvatarColor: a valid stored `#rrggbb` wins, otherwise the colour is derived
// from a stable identity (the user id), so the server and the client agree. Every palette colour carries white initials at 4.5:1 or better, in both themes;
// avatarInk() gives the initials' colour for any colour (a stored one may be light), white or black, whichever reads better.
export const avatarPalette = [
    '#0369a1',
    '#7c3aed',
    '#be185d',
    '#b45309',
    '#047857',
    '#b91c1c',
    '#4f46e5',
    '#0f766e',
] as const;

export function avatarColor(
    identity: string | number,
    stored?: string | null,
): string {
    if (stored && /^#[0-9a-f]{6}$/i.test(stored)) {
        return stored.toLowerCase();
    }
    let hash = 0;
    for (const character of String(identity)) {
        hash = (Math.imul(hash, 31) + character.codePointAt(0)!) | 0;
    }

    return avatarPalette[Math.abs(hash) % avatarPalette.length];
}

// The initials' colour on a disc: white, or black when that contrasts more (a light stored colour such as amber). Mirrors AvatarColor::ink(). Black, not a
// near-black: the better of white and black is 4.58:1 or more on any colour, so every colour, stored or derived, passes AA; a near-black misses on mid-greys.
export const avatarInkDark = '#000000';

export function avatarInk(color: string): string {
    const channel = (offset: number): number => {
        const value = parseInt(color.slice(offset, offset + 2), 16) / 255;
        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    };
    const l = 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
    return 1.05 / (l + 0.05) >= (l + 0.05) / 0.05 ? '#ffffff' : avatarInkDark;
}
