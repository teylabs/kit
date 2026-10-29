import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, readFileSync } from 'node:fs';

// The Toaster's rich colours: kit.css maps every Sonner rich-colour variable onto the kit's tone tokens, and each tone's text on its background meets WCAG AA
// (4.5:1) in light and dark. The tokens are read from kit.css itself; no browser.
const css = readFileSync(new URL('../../resources/features/core/css/kit.css.stub', import.meta.url), 'utf8');
const TONES = ['success', 'info', 'warning', 'error'];
const SONNER = TONES.flatMap(tone => ['bg', 'border', 'text'].map(part => `--${tone}-${part}`));

/** The custom properties every block with this selector sets, later blocks winning (as the cascade does). */
function block(selector) {
    const found = {};
    let start = css.indexOf(`${selector} {`);
    assert.ok(start >= 0, `kit.css has ${selector}`);
    while (start >= 0) {
        const body = css.slice(css.indexOf('{', start) + 1, css.indexOf('}', start));
        for (const match of body.matchAll(/(--[\w-]+):\s*([^;]+);/g)) found[match[1]] = match[2].trim();
        start = css.indexOf(`${selector} {`, start + 1);
    }
    return found;
}
const toaster = block(':is([data-kit-toaster][data-sonner-toaster], [data-kit-toaster] [data-sonner-toaster])[data-sonner-theme]');
const themes = { light: block(':where(:root)'), dark: block(':where(.dark)') };

function luminance(hex) {
    const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255).map(c => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
const contrast = (a, b) => { const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x); return (l1 + 0.05) / (l2 + 0.05); };
function hslToHex(h, s, l) {
    s /= 100; l /= 100;
    const f = n => { const k = (n + h / 30) % 12; const c = l - s * Math.min(l, 1 - l) * Math.max(-1, Math.min(k - 3, 9 - k, 1)); return Math.round(c * 255).toString(16).padStart(2, '0'); };
    return `#${f(0)}${f(8)}${f(4)}`;
}

test('every Sonner rich-colour variable is mapped onto a kit tone token', () => {
    assert.deepEqual(Object.keys(toaster).sort(), [...SONNER].sort());
    for (const name of SONNER) assert.match(toaster[name], /^var\(--kit-tone-(success|info|warning|error)-(bg|border|fg)\)$/, name);
    const sonnerCss = new URL('../../typecheck/node_modules/vue-sonner/lib/index.css', import.meta.url);
    if (existsSync(sonnerCss)) {
        const used = new Set([...readFileSync(sonnerCss, 'utf8').matchAll(/--(?:success|info|warning|error)-(?:bg|border|text)\b/g)].map(match => match[0]));
        assert.deepEqual([...used].sort(), [...SONNER].sort(), 'the list matches what vue-sonner uses');
    }
});

test('each tone token is defined for light and dark', () => {
    for (const [theme, tokens] of Object.entries(themes)) {
        for (const tone of TONES) for (const part of ['bg', 'border', 'fg']) assert.match(tokens[`--kit-tone-${tone}-${part}`] ?? '', /^#[0-9a-f]{6}$/, `${theme} --kit-tone-${tone}-${part}`);
    }
});

test('each tone\'s text meets AA (4.5:1) on its background, light and dark', () => {
    for (const [theme, tokens] of Object.entries(themes)) {
        for (const tone of TONES) {
            const ratio = contrast(tokens[`--kit-tone-${tone}-fg`], tokens[`--kit-tone-${tone}-bg`]);
            assert.ok(ratio >= 4.5, `${theme} ${tone}: ${ratio.toFixed(2)}:1`);
        }
    }
});

test('Sonner\'s own success colours fail AA, which is why they are mapped', () => {
    assert.ok(contrast(hslToHex(140, 100, 27), hslToHex(143, 85, 96)) < 4.5);
});

test('the selector outranks Sonner\'s two-attribute theme selector', () => {
    // :is() takes its most specific argument (two attributes), plus [data-sonner-theme]: three attributes against Sonner's two.
    assert.match(css, /:is\(\[data-kit-toaster\]\[data-sonner-toaster\], \[data-kit-toaster\] \[data-sonner-toaster\]\)\[data-sonner-theme\] \{/);
    assert.match(readFileSync(new URL('../../resources/features/core/components/Kit/Feedback/Toaster.vue.stub', import.meta.url), 'utf8'), /<Sonner [^>]*rich-colors data-kit-toaster/);
});
