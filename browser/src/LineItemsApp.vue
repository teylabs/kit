<script setup lang="ts">
import LineItems from '@/components/Kit/Table/LineItems.vue';

if (new URLSearchParams(window.location.search).get('line-items') === 'dark') document.documentElement.classList.add('dark');

// LineItems for run.mjs (?line-items): an invoice (5 columns, 3 lines, subtotal, tax and total, a cell slot) and a statement with a running balance and a
// row header column (`=dark` for the dark tokens), for the semantics, alignment, totals placement, axe and no page overflow at 390px.
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
</script>
<template>
    <main class="bg-background text-foreground grid max-w-3xl gap-10 p-4">
        <h1 class="text-xl font-semibold">Invoice 1042</h1>
        <LineItems :columns="invoiceColumns" :rows="invoiceRows" caption="Invoice 1042 lines" :totals="invoiceTotals" data-test-invoice>
            <template #cell-amount="{ value }"><output data-test-computed>{{ value }}</output></template>
            <template #footer><p class="text-muted-foreground text-xs">Due on receipt.</p></template>
        </LineItems>
        <LineItems :columns="statementColumns" :rows="statementRows" caption="Member statement, September to October 2026" caption-hidden row-header="date" :totals="[{ label: 'Balance owing', value: '$0.00', emphasis: true }]" data-test-statement />
        <LineItems :columns="invoiceColumns" :rows="invoiceRows" caption="Formatted totals" caption-hidden :totals="[{ label: 'Total', value: 45200, emphasis: true }]" data-test-total-slot>
            <template #total-value="{ total }"><span data-formatted-total>{{ (Number(total.value) / 100).toFixed(2) }} CAD</span></template>
        </LineItems>
        <LineItems :columns="statementColumns" :rows="[]" caption="Empty statement" caption-hidden empty="No entries this period." data-test-empty />
    </main>
</template>
