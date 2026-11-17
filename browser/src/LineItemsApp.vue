<script setup lang="ts">
import Card from '@/components/Kit/Card/Card.vue';
import LineItems from '@/components/Kit/Table/LineItems.vue';

if (new URLSearchParams(window.location.search).get('line-items') === 'dark') document.documentElement.classList.add('dark');

// LineItems for run.mjs (?line-items): an invoice (5 columns, 3 lines, subtotal, tax and total, a cell slot) and a statement with a running balance and a
// row header column, and a report with percent, empty cells, per-row currencies and sortable columns (`=dark` for the dark tokens), for the semantics, alignment, totals placement, axe and no page overflow at 390px.
const invoiceColumns = [
    { key: 'item', label: 'Item' },
    { key: 'description', label: 'Description' },
    { key: 'quantity', label: 'Qty', kind: 'number' as const },
    { key: 'price', label: 'Unit price', kind: 'money' as const },
    { key: 'amount', label: 'Amount', kind: 'money' as const },
];
const invoiceRows = [
    { item: 'Annual membership', description: 'Adult, 2026–27 season', quantity: 1, price: '$240.00', amount: '$240.00' },
    { item: 'Locker rental', description: 'Half-size locker, north change room', quantity: 2, price: '$35.00', amount: '$70.00' },
    { item: 'Guest passes', description: 'Book of ten', quantity: 1, price: '$90.00', amount: '$90.00' },
];
const invoiceTotals = [
    { label: 'Subtotal', value: '$400.00' },
    { label: 'Tax (13%)', value: '$52.00' },
    { label: 'Total', value: '$452.00', emphasis: true },
];
const statementColumns = [
    { key: 'date', label: 'Date', kind: 'date' as const },
    { key: 'entry', label: 'Entry' },
    { key: 'charge', label: 'Charge', kind: 'money' as const },
    { key: 'payment', label: 'Payment', kind: 'money' as const },
    { key: 'balance', label: 'Balance', kind: 'money' as const },
];
const statementRows = [
    { date: '2026-09-01', entry: 'Opening balance', charge: '', payment: '', balance: '$0.00' },
    { date: '2026-09-03', entry: 'Invoice 1042', charge: '$452.00', payment: '', balance: '$452.00' },
    { date: '2026-09-20', entry: 'Payment, card ending 4242', charge: '', payment: '$200.00', balance: '$252.00' },
    { date: '2026-10-05', entry: 'Payment, e-transfer', charge: '', payment: '$252.00', balance: '$0.00' },
];
// A report (ReportTable's props): computed rows with a percent column, empty cells, a per-row currency, sortable columns and totals that stay put.
const reportColumns = [
    { key: 'room', label: 'Room' },
    { key: 'nights', label: 'Nights', kind: 'number' as const, sortable: true },
    { key: 'revenue', label: 'Revenue', kind: 'money' as const, currency: 'CAD', currencyKey: 'currency', sortable: true },
    { key: 'occupancy', label: 'Occupancy', kind: 'percent' as const, sortable: true },
    { key: 'note', label: 'Note', sortable: true },
];
const reportRows = [
    { room: 'Harbour', nights: 12, revenue: '1840.50', currency: 'CAD', occupancy: 81.25, note: 'Busy' },
    { room: 'Garden', nights: 3, revenue: 420, currency: 'USD', occupancy: null, note: null },
    { room: 'Loft', nights: 20, revenue: '-35.5', currency: null, occupancy: 9.5, note: 'Adjusted' },
    { room: 'Annex', nights: null, revenue: null, currency: 'EUR', occupancy: 100, note: 'Closed' },
];
</script>
<template>
    <main class="bg-background text-foreground grid max-w-3xl gap-10 p-4">
        <h1 class="text-xl font-semibold">Invoice 1042</h1>
        <LineItems :columns="invoiceColumns" :rows="invoiceRows" caption="Invoice 1042 lines" :totals="invoiceTotals" data-test-invoice>
            <template #cell-amount="{ value }"><output data-test-computed>{{ value }}</output></template>
            <template #footer><p class="text-muted-foreground text-xs">Due on receipt.</p></template>
        </LineItems>
        <LineItems :columns="statementColumns" :rows="statementRows" caption="Member statement, September to October 2026" caption-hidden row-header="date" :totals="[{ label: 'Balance owing', value: '$0.00', emphasis: true }]" data-test-statement />
        <Card class="w-full max-w-96 p-4" data-test-narrow-card>
            <LineItems
                :columns="[{ key: 'item', label: 'Description' }, { key: 'qty', label: 'Qty', kind: 'number' }, { key: 'tax', label: 'Tax', align: 'end', nowrap: true }, { key: 'amount', label: 'Amount', kind: 'money' }]"
                :rows="[{ item: 'Annual membership', qty: 1, tax: 'HST (13 %)', amount: '$240.00' }, { item: 'Locker rental', qty: 2, tax: 'HST (13 %)', amount: '$70.00' }]"
                caption="Narrow invoice"
                caption-hidden
                :totals="[{ label: 'Total', value: '$350.30', emphasis: true }]"
            />
        </Card>
        <LineItems :columns="invoiceColumns" :rows="invoiceRows" caption="Formatted totals" caption-hidden :totals="[{ label: 'Total', value: 45200, emphasis: true }]" data-test-total-slot>
            <template #total-value="{ total }"><span data-formatted-total>{{ (Number(total.value) / 100).toFixed(2) }} CAD</span></template>
        </LineItems>
        <LineItems :columns="statementColumns" :rows="[]" caption="Empty statement" caption-hidden empty="No entries this period." data-test-empty />
        <LineItems :columns="reportColumns" :rows="reportRows" caption="Revenue by room" row-header="room" :totals="[{ label: 'Total', value: '$2,225.00', emphasis: true }]" data-test-report />
        <LineItems :columns="reportColumns" :rows="[]" caption="Empty report" caption-hidden data-test-empty-slot>
            <template #empty><span data-test-empty-text>No stays in this period.</span></template>
        </LineItems>
    </main>
</template>
