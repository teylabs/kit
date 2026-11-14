<script setup lang="ts">
// The Text family's props, its helpers and its deprecated aliases, as a host would use them on a record page. Compiled by bin/typecheck-components.sh;
// never rendered.
import {
    AddressText,
    badgeItems,
    ClickToCopy,
    CopyButton,
    CopyField,
    copyText,
    formatNumeric,
    LongText,
    longTextCut,
    ProseCode,
    ProseTable,
    RawOutputCard,
    TextAddress,
    TextChips,
    TextCode,
    TextCopy,
    TextDollars,
    TextLong,
    TextNumeric,
    TextProse,
    TextTable,
    TextTags,
    TextTruncate,
    textLines,
    type ClampLines,
    type CopyState,
    type TextChip,
    type TextSize,
} from '@kit/ui';
import { ref } from 'vue';

const open = ref(false);
const size: TextSize = 'base';
const lines: ClampLines = 3;
const state: CopyState = 'idle';
const chips: TextChip[] = ['Draft', { label: 'Plan', value: 'Pro', tone: 'info' }];
const shown: string | null = formatNumeric('1234.5', { decimals: 2, locale: 'fr-CA' });
const cut: { shown: string; truncated: boolean } = longTextCut('A long body', 4);
const rows: string[] = textLines('1 Main St, Ottawa ON');
const keys: string[] = badgeItems(chips).map((item) => item.key);
const written: Promise<void> = copyText('abc');
</script>
<template>
    <TextProse text="Line one" :size="size" />
    <TextProse><p>Markup the app renders.</p></TextProse>
    <TextCode content="{}" title="Payload" card max-height="lg" :wrap="false" :level="2" />
    <TextCode toolbar>log line</TextCode>
    <TextTable label="Totals"><table /></TextTable>
    <TextLong v-model:expanded="open" text="A long body" :limit="120" more-label="More" less-label="Less" />
    <TextTruncate text="A long title" :lines="lines" max-width="16rem" />
    <TextCopy :value="42" tooltip="Copy the id" />
    <TextCopy value="abc" button label="Copy key" copied-label="Copied key" variant="ghost" />
    <CopyField label="API key" value="abc" description="Keep it secret." multiline id="api-key" />
    <CopyButton value="abc" icon label="Copy key" />
    <TextNumeric :value="1234" :decimals="0" notation="compact" prefix="~" suffix=" items" negative="parentheses" />
    <TextAddress :lines="['1 Main St', 'Ottawa ON']" size="sm" />
    <TextAddress :address="{ line1: '1 Main St', country: 'CA' }" hide-country />
    <AddressText :address="null" locale="fr-CA" />
    <TextChips :items="chips" label="Details" size="sm" />
    <TextTags :items="['a', 'b']" label="Tags" tone="info" />
    <ProseCode content="x" />
    <ProseTable><table /></ProseTable>
    <LongText text="x" />
    <ClickToCopy value="x" />
    <RawOutputCard title="Output" content="x" />
    <TextDollars :value="12.5" :symbol="false" />
    <span>{{ shown }}{{ cut.shown }}{{ rows.length }}{{ keys.length }}{{ state }}{{ written }}</span>
    <!-- @vue-expect-error value is required -->
    <TextCopy tooltip="Copy" />
    <!-- @vue-expect-error lines is 1 to 6 -->
    <TextTruncate text="x" :lines="7" />
    <!-- @vue-expect-error max-height is sm, md, lg or none -->
    <TextCode content="x" max-height="xl" />
    <!-- @vue-expect-error a tag is a string -->
    <TextTags :items="[1]" />
</template>
