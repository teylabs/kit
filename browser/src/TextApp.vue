<script setup lang="ts">
import TextAddress from '@/components/Kit/Text/TextAddress.vue';
import TextChips from '@/components/Kit/Text/TextChips.vue';
import TextCode from '@/components/Kit/Text/TextCode.vue';
import TextCopy from '@/components/Kit/Text/TextCopy.vue';
import CopyField from '@/components/Kit/Text/CopyField.vue';
import TextLong from '@/components/Kit/Text/TextLong.vue';
import TextNumeric from '@/components/Kit/Text/TextNumeric.vue';
import TextProse from '@/components/Kit/Text/TextProse.vue';
import TextTable from '@/components/Kit/Text/TextTable.vue';
import TextTags from '@/components/Kit/Text/TextTags.vue';
import TextTruncate from '@/components/Kit/Text/TextTruncate.vue';
import AddressText from '@/components/Kit/Form/AddressText.vue';
import CopyButton from '@/components/Kit/Action/CopyButton.vue';
import { defineComponent, h, ref } from 'vue';

// The Text family for run.mjs (?text, ?text=dark under .dark): each component in a [data-case] the tests read, resize or press. The values are synthetic.
if (new URLSearchParams(window.location.search).get('text') === 'dark') document.documentElement.classList.add('dark');
const log = Array.from({ length: 60 }, (_, index) => `${String(index + 1).padStart(3, '0')} worker.info job=export-${index + 1} status=done rows=${(index + 1) * 120} <b>not markup</b> ${'x'.repeat(index % 7 === 0 ? 180 : 10)}`).join('\n');
const longBody = `First paragraph of a long note about the quarterly figures.\n\nSecond paragraph, which goes on for a while so the cut lands inside it: ${'more words '.repeat(30)}and ends here. 👨‍👩‍👧‍👦`;
const title = 'Quarterly board report appendix with every table and the minutes of the meeting';
// Slot text that changes two ways: from this page ([data-slot-swap] re-renders TextCode's slot), and from a child that writes its own DOM outside Vue's
// render, as a highlighter or a log stream would ([data-slot-tick]), which no update hook of TextCode sees.
const slotLine = ref('build 1 started');
const Ticker = defineComponent({
    setup() {
        const label = ref<HTMLElement | null>(null);
        let count = 0;
        const tick = () => {
            if (label.value) label.value.textContent = `tick ${++count} `;
        };
        return () => h('span', [h('span', { ref: label }, 'tick 0 '), h('button', { type: 'button', 'data-slot-tick': '', onClick: tick }, 'Tick')]);
    },
});
</script>
<template>
    <main class="bg-background text-foreground min-h-screen p-4 text-sm">
        <h1 class="mb-4 text-lg font-semibold">Text family</h1>
        <div class="grid max-w-3xl gap-6">
            <section data-case="copy">
                <p>Reference: <TextCopy value="INV-2026-0042" data-inline-copy /></p>
                <p>Order <TextCopy value="ORD-9" tooltip="Copy order number">#9</TextCopy></p>
                <CopyButton value="tok_button_value" label="Copy token" />
                <CopyField label="API key" value="key-demo-0000-0000-0000" description="Keep it secret." data-copy-field />
            </section>
            <section data-case="truncate">
                <div data-truncate-box class="w-40 rounded-md border p-2">
                    <TextTruncate :text="title" data-truncate-one />
                </div>
                <div data-clamp-box class="w-60 rounded-md border p-2">
                    <TextTruncate :lines="2" data-truncate-two>{{ title }} {{ title }}</TextTruncate>
                </div>
                <TextTruncate text="Short" max-width="16rem" data-truncate-short />
            </section>
            <section data-case="long">
                <TextLong :text="longBody" :limit="80" data-long />
                <TextLong text="Short enough." :limit="80" data-long-short />
            </section>
            <section data-case="code">
                <TextCode :content="log" title="Export log" card :level="2" data-code-card />
                <TextCode content='{"ok":true}' data-code-plain />
                <TextCode toolbar title="Build log" data-code-slot>{{ slotLine }} <Ticker /></TextCode>
                <button type="button" data-slot-swap @click="slotLine = 'build 2 started'">Next build</button>
            </section>
            <section data-case="numbers">
                <ul>
                    <li><TextNumeric :value="1234567" locale="en-CA" data-n="en" /></li>
                    <li><TextNumeric value="1234.5" :decimals="2" locale="fr-CA" data-n="fr" /></li>
                    <li><TextNumeric :value="1234" notation="compact" locale="en-CA" data-n="compact" /></li>
                    <li><TextNumeric value="42" locale="en-CA" suffix=" items" data-n="suffix" /></li>
                    <li><TextNumeric :value="-1234.5" :decimals="2" locale="en-CA" negative="parentheses" data-n="accounting" /></li>
                    <li><TextNumeric value="-42" locale="en-CA" data-n="minus" /></li>
                    <li data-n-empty>[<TextNumeric value="n/a" />]</li>
                </ul>
            </section>
            <section data-case="address">
                <TextAddress :address="{ line1: '1 Example Way', line2: 'Unit 4', city: 'Gatineau', region: 'QC', postal_code: 'J8X 0A1', country: 'CA' }" locale="fr-CA" data-address="fr" />
                <TextAddress lines="200 Sample Ave, Suite 3, Springfield IL 62701" size="base" data-address="lines" />
                <AddressText :address="{ line1: '1 Example Way', city: 'Gatineau', region: 'QC', postal_code: 'J8X 0A1', country: 'CA' }" locale="en-CA" hide-country data-address="alias" />
            </section>
            <section data-case="badges">
                <TextChips label="Details" :items="['Draft', { label: 'Plan', value: 'Pro', tone: 'info' }, { label: 'Overdue', tone: 'danger' }]" />
                <TextTags label="Tags" :items="['design', 'q3', '', 'design']" />
            </section>
            <section data-case="prose">
                <TextProse text="Line one&#10;Line two <b>kept as text</b>" data-prose-text />
                <TextProse size="base"><p>A paragraph with <a href="#prose">a link</a>.</p><ul><li>One</li><li>Two</li></ul></TextProse>
                <TextTable label="Totals">
                    <table>
                        <thead><tr><th>Item</th><th>Count</th></tr></thead>
                        <tbody><tr><td>Exports</td><td>12</td></tr><tr><td>Imports</td><td>4</td></tr></tbody>
                    </table>
                </TextTable>
            </section>
        </div>
    </main>
</template>
