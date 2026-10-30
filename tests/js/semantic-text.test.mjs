import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Status text and icons on a surface (FormActions' "Saved", an Alert's icon) use --success-text, --warning-text and --info-text, as destructive text uses
// --destructive-text: kit.css maps each to a `text-*-text` utility and sets it for light and dark. A raw palette colour as text (`text-green-600`, 3.3:1 on white)
// ignores the host theme and fails WCAG AA, so no kit resource may use one. The ratios are computed here from kit.css itself, so changing a value can't silently regress.
const resources = fileURLToPath(new URL('../../resources', import.meta.url));
const css = readFileSync(path.join(resources, 'features/core/css/kit.css.stub'), 'utf8');

const files = (dir) => readdirSync(dir).flatMap((name) => {
    const file = path.join(dir, name);
    return statSync(file).isDirectory() ? files(file) : /\.(stub|vue|ts|js|css|php)$/.test(name) ? [file] : [];
});

/** The custom properties a block with this selector sets: the colour block, found by content (kit.css has other :where(:root) blocks). */
function block(selector) {
    const start = css.indexOf(`${selector} {\n    --background:`);
    assert.ok(start >= 0, `kit.css has the ${selector} colour block`);
    const body = css.slice(css.indexOf('{', start) + 1, css.indexOf('}', start));
    return Object.fromEntries([...body.matchAll(/(--[\w-]+):\s*([^;]+);/g)].map(match => [match[1], match[2].trim()]));
}

function rgb(value) {
    const match = value.match(/^hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)$/);
    assert.ok(match, `an hsl() colour: ${value}`);
    const [h, s, l] = [Number(match[1]), Number(match[2]) / 100, Number(match[3]) / 100];
    const f = n => { const k = (n + h / 30) % 12; return Math.round((l - s * Math.min(l, 1 - l) * Math.max(-1, Math.min(k - 3, 9 - k, 1))) * 255) / 255; };
    return [f(0), f(8), f(4)];
}
const luminance = c => { const [r, g, b] = c.map(v => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4)); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
const contrast = (a, b) => { const [l1, l2] = [luminance(rgb(a)), luminance(rgb(b))].sort((x, y) => y - x); return (l1 + 0.05) / (l2 + 0.05); };

// Each token keeps its meaning: its hue stays in its family (green, amber/orange, blue).
const TOKENS = { '--success-text': [120, 160], '--warning-text': [20, 50], '--info-text': [190, 220] };

test('kit.css maps --color-success-text, --color-warning-text and --color-info-text at default precedence', () => {
    const theme = css.slice(css.indexOf('@theme default inline {'));
    for (const token of Object.keys(TOKENS)) {
        assert.match(theme.slice(0, theme.indexOf('}')), new RegExp(`--color-${token.slice(2)}: var\\(${token}\\);`));
    }
});

test('--success-text, --warning-text and --info-text meet AA (4.5:1) on background, card, popover, accent and muted, light and dark, in their own hue', () => {
    for (const [mode, selector] of [['light', ':where(:root)'], ['dark', ':where(.dark)']]) {
        const tokens = block(selector);
        for (const [token, [low, high]] of Object.entries(TOKENS)) {
            const text = tokens[token];
            assert.ok(text, `${mode} sets ${token}`);
            const hue = Number(text.match(/^hsl\(([\d.]+) /)[1]);
            assert.ok(hue >= low && hue <= high, `${mode} ${token} keeps its hue: ${hue}`);
            for (const surface of ['background', 'card', 'popover', 'accent', 'muted']) {
                const ratio = contrast(text, tokens[`--${surface}`]);
                assert.ok(ratio >= 4.5, `${mode} ${token} on --${surface}: ${ratio.toFixed(2)}:1`);
            }
        }
    }
});

test('the old green-600 "Saved" colour would fail, so the test can tell the tokens apart', () => {
    assert.ok(contrast('hsl(142 76% 36%)', block(':where(:root)')['--card']) < 4.5);
});

test('no kit resource colours text with a raw palette colour; it uses a semantic token', () => {
    const palette = 'red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone';
    const raw = new RegExp(`(?<![\\w-])((?:[\\w-]+:)*text-(?:${palette})-\\d{2,3})(?![\\w-])`, 'g');
    const found = files(resources).flatMap(file => [...readFileSync(file, 'utf8').matchAll(raw)].map(match => `${path.relative(resources, file)}: ${match[1]}`));
    assert.deepEqual(found, []);
    assert.match(readFileSync(path.join(resources, 'features/core/components/Kit/Form/FormActions.vue.stub'), 'utf8'), /saved: 'text-success-text'/, 'FormActions shows "Saved" in --success-text');
});
