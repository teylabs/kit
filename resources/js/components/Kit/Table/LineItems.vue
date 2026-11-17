<script setup lang="ts">
import { computed, useId } from 'vue';

/*
 * A frozen document's table (docs/line-items.md): an invoice's lines, the same lines on a public share page, a statement with a running balance. Not an
 * index: no sorting, selection or pagination (DataTable does those). Real <table> semantics: a <caption> (visible, or sr-only with `captionHidden`),
 * <th scope=col> headers, and with `rowHeader` that column's cells are <th scope=row>. money and number columns are end-aligned with tabular figures; values
 * render as given (format them on the server, or fill `#cell-<key>="{ row, value }"`). Only the first text column (the one that names the row, sticky on a phone) keeps a minimum width; other text columns shrink to their content. `totals` sit in a <tfoot> (`#total-value="{ total, index }"` renders a value, e.g. through Amount, like the cells above it), each value under the last end-aligned
 * column. On a narrow page the table scrolls sideways inside its own box (a focusable region named by the caption), with the first column kept in view.
 */
export type LineItemsColumn = {
    key: string;
    label: string;
    align?: 'start' | 'end' | 'center';
    kind?: 'text' | 'money' | 'number' | 'date';
};
export type LineItemsTotal = {
    label: string;
    value: string | number;
    emphasis?: boolean;
};

const props = withDefaults(
    defineProps<{
        columns: LineItemsColumn[];
        rows: Record<string, unknown>[];
        caption: string;
        captionHidden?: boolean;
        rowHeader?: string;
        totals?: LineItemsTotal[];
        empty?: string;
    }>(),
    {
        captionHidden: false,
        rowHeader: undefined,
        totals: () => [],
        empty: 'Nothing here.',
    },
);

const captionId = useId();

const alignOf = (column: LineItemsColumn) =>
    column.align ??
    (column.kind === 'money' || column.kind === 'number' ? 'end' : 'start');
const ALIGN = {
    start: 'text-start',
    end: 'text-end',
    center: 'text-center',
} as const;
const cellClass = (column: LineItemsColumn, index: number) => [
    'border-b border-border px-3 py-2 align-top',
    ALIGN[alignOf(column)],
    column.kind === 'money' ||
    column.kind === 'number' ||
    column.kind === 'date'
        ? 'tabular-nums whitespace-nowrap'
        : index === 0
          ? 'min-w-40'
          : '',
    // The sticky first column is painted in its surface's colour: --avatar-ring is the colour each surface (a card, a popover, a dialog) sets for what sits on it.
    index === 0 ? 'sticky start-0 bg-(--avatar-ring,var(--background))' : '',
];

// The totals' value column: the last end-aligned one (the amount), else the last column. The label spans the columns before it.
const totalsIndex = computed(() => {
    const ends = props.columns
        .map((column, index) => (alignOf(column) === 'end' ? index : -1))
        .filter((index) => index >= 0);
    return ends.length ? ends[ends.length - 1] : props.columns.length - 1;
});
const display = (value: unknown) =>
    value === null || value === undefined ? '' : String(value);
</script>
<template>
    <div
        role="region"
        :aria-labelledby="captionId"
        tabindex="0"
        class="focus-visible:outline-ring w-0 min-w-full overflow-x-auto rounded-sm text-sm focus-visible:outline-2 focus-visible:outline-offset-2"
        data-slot="line-items"
    >
        <table class="w-full border-collapse">
            <caption
                :id="captionId"
                :class="
                    captionHidden
                        ? 'sr-only'
                        : 'text-foreground px-3 pb-2 text-start font-medium'
                "
            >
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr>
                    <th
                        v-for="(column, index) in columns"
                        :key="column.key"
                        scope="col"
                        :class="[
                            cellClass(column, index),
                            'text-muted-foreground font-medium whitespace-nowrap',
                        ]"
                        :data-column="column.key"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!rows.length">
                    <td
                        :colspan="columns.length"
                        class="text-muted-foreground px-3 py-6 text-center"
                        data-line-items-empty
                    >
                        {{ empty }}
                    </td>
                </tr>
                <tr v-for="(row, rowIndex) in rows" :key="rowIndex">
                    <component
                        :is="column.key === rowHeader ? 'th' : 'td'"
                        v-for="(column, index) in columns"
                        :key="column.key"
                        :scope="column.key === rowHeader ? 'row' : undefined"
                        :class="[
                            cellClass(column, index),
                            'text-foreground font-normal',
                        ]"
                        :data-column="column.key"
                    >
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                            :value="row[column.key]"
                            >{{ display(row[column.key]) }}</slot
                        >
                    </component>
                </tr>
            </tbody>
            <tfoot v-if="totals.length || $slots.footer">
                <tr
                    v-for="(total, index) in totals"
                    :key="total.label"
                    :data-total="total.label"
                    :class="
                        total.emphasis
                            ? 'text-foreground font-semibold'
                            : 'text-muted-foreground font-normal'
                    "
                >
                    <th
                        scope="row"
                        :colspan="Math.max(totalsIndex, 1)"
                        class="px-3 py-1.5 text-end"
                        :class="
                            total.emphasis ? 'font-semibold' : 'font-normal'
                        "
                    >
                        {{ total.label }}
                    </th>
                    <td
                        class="px-3 py-1.5 text-end whitespace-nowrap tabular-nums"
                        :class="total.emphasis ? 'border-border border-t' : ''"
                        data-total-value
                    >
                        <slot
                            name="total-value"
                            :total="total"
                            :index="index"
                            >{{ display(total.value) }}</slot
                        >
                    </td>
                    <td
                        v-if="totalsIndex < columns.length - 1"
                        :colspan="columns.length - 1 - totalsIndex"
                    />
                </tr>
                <tr v-if="$slots.footer">
                    <td :colspan="columns.length" class="px-3 py-2">
                        <slot name="footer" />
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
