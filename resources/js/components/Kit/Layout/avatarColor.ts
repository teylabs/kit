// One colour per person (docs/avatars.md). Mirrors Tey\Kit\Features\Core\AvatarColor: a valid stored `#rrggbb` wins, otherwise the colour is derived
// from a stable identity (the user id), so the server and the client agree. Every colour carries white initials at 4.5:1 or better, in both themes.
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
