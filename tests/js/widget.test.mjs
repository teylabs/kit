import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

// Widget, WidgetHeader and SegmentLinks (docs/widgets.md), checked by source; the browser job (browser/run.mjs, ?widget) checks them rendered.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = (path) => readFileSync(new URL(path, kit), 'utf8');

test('Widget, WidgetHeader and SegmentLinks are exported with their types', () => {
    const index = read('index.ts');
    for (const line of [
        "export { default as Widget } from './Layout/Widget.vue';",
        "export { default as WidgetHeader } from './Layout/WidgetHeader.vue';",
        "export { default as SegmentLinks } from './Layout/SegmentLinks.vue';",
        'WidgetDescriptor',
        'SegmentLink }',
    ])
        assert.ok(index.includes(line), line);
});

test('Widget draws its own card, EmptyState, a loading skeleton and every slot', () => {
    const widget = read('Layout/Widget.vue');
    for (const needle of ['bg-card', '<EmptyState', 'aria-busy', 'motion-safe:animate-pulse', "actionPlacement: 'header'", 'useGroundSurface', 'data-widget-footer'])
        assert.ok(widget.includes(needle), needle);
    for (const slot of ['title', 'avatar', 'meta', 'badge', 'actions']) assert.ok(widget.includes(`name="${slot}"`), slot);
});

test('SegmentLinks are links with aria-current and a navigate emit', () => {
    const links = read('Layout/SegmentLinks.vue');
    for (const needle of ['<Link', "'page'", "emit('navigate', $event, segment.href)", 'preserve-state', "appearance: 'link'", '<ColourDot'])
        assert.ok(links.includes(needle), needle);
});
