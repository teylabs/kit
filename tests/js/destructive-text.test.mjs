import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Destructive text on a surface (a destructive menu item, an error message) uses --destructive-text, which kit.css maps to `text-destructive-text` and sets for
// light and dark; `--destructive` as text fails WCAG AA on white. The ratios are computed here from kit.css itself, so changing a value can't silently regress.
const features = fileURLToPath(new URL('../../resources/features', import.meta.url));
const css = readFileSync(path.join(features, 'core/css/kit.css.stub'), 'utf8');

const stubs = (dir) => readdirSync(dir).flatMap((name) => {
    const file = path.join(dir, name);
    return statSync(file).isDirectory() ? stubs(file) : name.endsWith('.stub') ? [file] : [];
});

/** The custom properties a block with this selector sets. */
function block(selector) {
    const start = css.indexOf(`${selector} {`);
    assert.ok(start >= 0, `kit.css has ${selector}`);
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

test('kit.css maps --color-destructive-text and keeps --destructive-foreground for text on a destructive background', () => {
    const theme = css.slice(css.indexOf('@theme default inline {'));
    assert.match(theme.slice(0, theme.indexOf('}')), /--color-destructive-text: var\(--destructive-text\);/);
    assert.match(theme.slice(0, theme.indexOf('}')), /--color-destructive-foreground: var\(--destructive-foreground\);/);
});

test('--destructive-text meets AA (4.5:1) on background, card, popover, accent and muted, light and dark, and stays red', () => {
    for (const [mode, selector] of [['light', ':where(:root)'], ['dark', ':where(.dark)']]) {
        const tokens = block(selector);
        const text = tokens['--destructive-text'];
        assert.ok(text, `${mode} sets --destructive-text`);
        assert.match(text, /^hsl\(0 /, `${mode} keeps the destructive hue`);
        for (const surface of ['background', 'card', 'popover', 'accent', 'muted']) {
            const ratio = contrast(text, tokens[`--${surface}`]);
            assert.ok(ratio >= 4.5, `${mode} on --${surface}: ${ratio.toFixed(2)}:1`);
        }
    }
});

test('the old --destructive as text would fail, so the test can tell the tokens apart', () => {
    const light = block(':where(:root)');
    assert.ok(contrast(light['--destructive'], light['--popover']) < 4.5);
});

test('every destructive text colour in the kit stubs uses text-destructive-text, never raw text-destructive', () => {
    const raw = stubs(features).flatMap(file => [...readFileSync(file, 'utf8').matchAll(/(?:^|[\s"'`:])((?:[\w-]+:)*text-destructive)(?![\w-])/g)].map(match => `${path.relative(features, file)}: ${match[1]}`));
    assert.deepEqual(raw, []);
    for (const file of ['core/components/Kit/Action/ActionMenuItems.vue.stub', 'core/components/Kit/Table/RowActions.vue.stub']) {
        assert.match(readFileSync(path.join(features, file), 'utf8'), /'text-destructive-text focus:text-destructive-text'/, file);
    }
    assert.match(readFileSync(path.join(features, 'core/components/Kit/Form/Repeater.vue.stub'), 'utf8'), /variant="destructive" class="text-destructive-text focus:text-destructive-text"/, 'the host DropdownMenuItem in Repeater is recoloured too');
});
