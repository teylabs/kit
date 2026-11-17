<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { numberDefaults, numberFormat } from '@/components/Kit/Form/number';
import { isNumeric, numericParts } from '@/components/Kit/Text/text';

/*
 * A frozen document's table (docs/line-items.md): an invoice's lines, the same lines on a public share page, a statement with a running balance. Not an
 * index: no sorting, selection or pagination (DataTable does those). Real <table> semantics: a <caption> (visible, or sr-only with `captionHidden`),
 * <th scope=col> headers, and with `rowHeader` that column's cells are <th scope=row>. money and number columns are end-aligned with tabular figures; values
 * render as given (format them on the server, or fill `#cell-<key>="{ row, value }"`). Only the first text column (the one that names the row, sticky on a phone) keeps a minimum width; other text columns shrink to their content. `totals` sit in a <tfoot> (`#total-value="{ total, index }"` renders a value, e.g. through Amount, like the cells above it), each value under the last end-aligned
 * column. On a narrow page the table scrolls sideways inside its own box (a focusable region named by the caption), with the first column kept in view.
 * Report tables (computed rows, ReportTable on the server): a numeric money cell is formatted in its row's currency (`currencyKey`, else the column's
 * `currency`, else the kit's default) and a numeric `percent` cell in percentage points, both in the kit's locale; any other value renders as given. A null
 * cell is an em dash read as "none". A `sortable` column's header is a button that sorts the rows here (aria-sort on its <th>): number, money and percent
 * numerically, empty cells last either way; the totals stay put. `#empty` replaces the `empty` line.
 */
export type LineItemsColumn = {
    key: string;
    label: string;
    align?: 'start' | 'end' | 'center';
    kind?: 'text' | 'money' | 'number' | 'percent' | 'date';
    /** A short text column (a tax label) that should stay on one line. */
    nowrap?: boolean;
    /** A money column's ISO currency for every row (a row's `currencyKey` field wins; else the kit's default). */
    currency?: string | null;
    /** A money column's row field holding that row's ISO currency. */
    currencyKey?: string | null;
    /** The header sorts the rows in the browser. */
    sortable?: boolean | null;
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

const NUMERIC_KINDS = ['money', 'number', 'percent'];
const alignOf = (column: LineItemsColumn) =>
    column.align ??
    (NUMERIC_KINDS.includes(column.kind ?? '') ? 'end' : 'start');
const ALIGN = {
    start: 'text-start',
    end: 'text-end',
    center: 'text-center',
} as const;
const cellClass = (column: LineItemsColumn, index: number) => [
    'border-b border-border px-3 py-2 align-top',
    ALIGN[alignOf(column)],
    NUMERIC_KINDS.includes(column.kind ?? '') || column.kind === 'date'
        ? 'tabular-nums whitespace-nowrap'
        : index === 0
          ? 'min-w-28'
          : column.nowrap
            ? 'whitespace-nowrap'
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
const isNone = (value: unknown) => value === null || value === undefined;

// Money and percent in the kit's locale and currency (kitNumber, then the browser, then en-CA), as Amount shows them; money in major units ("1840.50").
const page = usePage();
const defaults = computed(() =>
    numberDefaults(
        page.props?.kitNumber as
            { locale?: string; currency?: string } | undefined,
        typeof navigator === 'undefined' ? null : navigator.language,
    ),
);
const currencyOf = (column: LineItemsColumn, row: Record<string, unknown>) => {
    const own = column.currencyKey ? row[column.currencyKey] : null;
    return (
        (typeof own === 'string' && own ? own : null) ??
        column.currency ??
        defaults.value.currency
    );
};
function formatted(column: LineItemsColumn, row: Record<string, unknown>) {
    const value = row[column.key];
    if (
        (column.kind !== 'money' && column.kind !== 'percent') ||
        !isNumeric(value)
    )
        return display(value);
    const locale = defaults.value.locale;
    try {
        const codec = numberFormat({
            kind: column.kind,
            locale,
            currency:
                column.kind === 'money'
                    ? (currencyOf(column, row) ?? undefined)
                    : undefined,
        });
        const parts = numericParts(value, {
            locale,
            decimals: column.kind === 'money' ? codec.scale : undefined,
        });
        if (!parts) return display(value);
        const { prefix, suffix } = codec.affix;
        return `${parts.negative ? parts.minus : ''}${prefix}${parts.text}${suffix}`;
    } catch {
        // An unknown currency code: the value as given rather than nothing.
        return display(value);
    }
}

// Sorting in the browser, by one sortable column. Empty cells (null, undefined, '') go last in either direction; ties keep the server's order.
const sortKey = ref<string | null>(null);
const descending = ref(false);
const sortOf = (column: LineItemsColumn) =>
    sortKey.value === column.key
        ? descending.value
            ? 'descending'
            : 'ascending'
        : undefined;
function sortBy(column: LineItemsColumn) {
    descending.value = sortKey.value === column.key ? !descending.value : false;
    sortKey.value = column.key;
}
const sortValue = (column: LineItemsColumn, value: unknown) => {
    if (value === null || value === undefined || value === '') return null;
    if (!NUMERIC_KINDS.includes(column.kind ?? '')) return String(value);
    const number = isNumeric(value)
        ? Number(value)
        : Number(String(value).replace(/[^\d.-]/g, ''));
    return Number.isFinite(number) ? number : null;
};
const shownRows = computed(() => {
    const column = props.columns.find(
        (item) => item.key === sortKey.value && item.sortable,
    );
    if (!column) return props.rows;
    const direction = descending.value ? -1 : 1;
    return props.rows
        .map((row, index) => ({
            row,
            index,
            key: sortValue(column, row[column.key]),
        }))
        .sort((a, b) => {
            if (a.key === null || b.key === null)
                return a.key === b.key
                    ? a.index - b.index
                    : a.key === null
                      ? 1
                      : -1;
            const order =
                typeof a.key === 'number' && typeof b.key === 'number'
                    ? a.key - b.key
                    : String(a.key).localeCompare(String(b.key), undefined, {
                          numeric: true,
                      });
            return order * direction || a.index - b.index;
        })
        .map((item) => item.row);
});
</script>
<template>
    <!-- relative: an empty cell's sr-only "none" (absolutely positioned) stays inside the scroll box, never widening the page. -->
    <div
        role="region"
        :aria-labelledby="captionId"
        tabindex="0"
        class="focus-visible:outline-ring relative w-0 min-w-full overflow-x-auto rounded-sm text-sm focus-visible:outline-2 focus-visible:outline-offset-2"
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
                        :aria-sort="
                            column.sortable ? sortOf(column) : undefined
                        "
                    >
                        <button
                            v-if="column.sortable"
                            type="button"
                            class="hover:text-foreground focus-visible:outline-ring inline-flex items-center gap-1 rounded-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2"
                            data-line-items-sort
                            @click="sortBy(column)"
                        >
                            {{ column.label
                            }}<component
                                :is="
                                    sortOf(column) === 'ascending'
                                        ? ArrowUp
                                        : sortOf(column) === 'descending'
                                          ? ArrowDown
                                          : ArrowUpDown
                                "
                                class="size-3.5 shrink-0"
                                :class="sortOf(column) ? '' : 'opacity-50'"
                                aria-hidden="true"
                            />
                        </button>
                        <template v-else>{{ column.label }}</template>
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
                        <slot name="empty">{{ empty }}</slot>
                    </td>
                </tr>
                <tr v-for="(row, rowIndex) in shownRows" :key="rowIndex">
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
                            ><template v-if="isNone(row[column.key])"
                                ><span aria-hidden="true" data-line-items-none
                                    >—</span
                                ><span class="sr-only">none</span></template
                            ><template v-else>{{
                                formatted(column, row)
                            }}</template></slot
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
