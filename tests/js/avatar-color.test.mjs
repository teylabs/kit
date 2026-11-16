import assert from 'node:assert/strict';
import test from 'node:test';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// One avatar colour per person: avatarColor.ts and Tey\Kit\Features\Core\AvatarColor agree for every identity, every palette colour carries white
// initials and shows on both themes' pages, and Avatar draws the colour instead of the neutral disc. The browser job (?avatar-color) checks the rendered disc.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-avatar-color-'));
writeFileSync(join(dir, 'avatarColor.ts'), read('Layout/avatarColor.ts'));
const { avatarColor, avatarPalette } = await import(pathToFileURL(join(dir, 'avatarColor.ts')).href);
const php = fileURLToPath(new URL('../../src/Features/Core/AvatarColor.php', import.meta.url));

const identities = [1, 2, 3, 7, 42, 1096, 2147483647, '9f0c2a1e-77b3-4c1d-9e2f-0a1b2c3d4e5f', 'user:17', 'Zoë', '名前', '🙂'];

test('the server and the client derive the same colour for an identity', () => {
    const script = `require ${JSON.stringify(php)}; echo json_encode(array_map(fn ($i) => Tey\\Kit\\Features\\Core\\AvatarColor::for($i), json_decode($argv[1], true)));`;
    const server = JSON.parse(execFileSync('php', ['-r', script, JSON.stringify(identities)], { encoding: 'utf8' }));
    assert.deepEqual(
        identities.map(identity => avatarColor(identity)),
        server,
    );
});

test('a person keeps one colour, and different people spread across the palette', () => {
    assert.equal(avatarColor(42), avatarColor('42'));
    assert.equal(avatarColor(42), avatarColor(42));
    const seen = new Set(Array.from({ length: 64 }, (_, i) => avatarColor(i + 1)));
    assert.equal(seen.size, avatarPalette.length);
});

test('a stored colour wins; an invalid one is ignored', () => {
    assert.equal(avatarColor(1, '#AABBCC'), '#aabbcc');
    assert.equal(avatarColor(1, 'red'), avatarColor(1));
    assert.equal(avatarColor(1, null), avatarColor(1));
});

const luminance = hex => {
    const channel = offset => {
        const value = parseInt(hex.slice(offset, offset + 2), 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
};
const ratio = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

test('every palette colour carries white initials (4.5:1) and stands out on light and dark pages (3:1)', () => {
    for (const color of avatarPalette) {
        const l = luminance(color);
        assert.ok(ratio(l, 1) >= 4.5, `${color} white initials`);
        assert.ok(ratio(l, luminance('#ffffff')) >= 3, `${color} on a light page`);
        assert.ok(ratio(l, luminance('#09090b')) >= 3, `${color} on a dark page`);
    }
});

test('Avatar draws the person colour with white initials, and the neutral disc without one', () => {
    const source = read('Layout/Avatar.vue');
    assert.match(source, /color\?: string \| null;/);
    assert.match(source, /identity\?: string \| number \| null;/);
    assert.match(source, /disc\s*\?\s*'bg-\(--avatar-color\) text-white'\s*:\s*'bg-accent text-accent-foreground'/);
    assert.match(source, /'--avatar-color': disc/);
    for (const file of ['Comment/NoteCard.vue', 'Comment/CommentForm.vue']) {
        assert.match(read(file), /:color="author!?\.avatar_color"/, file);
    }
    assert.match(read('Layout/SectionHeader.vue'), /:color="avatar\.color"/);
});
