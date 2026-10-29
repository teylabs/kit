<script setup lang="ts">
// A status cell with Badge, and a DataTable naming its rows by a column key and by a function. Compiled by bin/typecheck-components.sh; never rendered.
import Badge from '@/components/Kit/Feedback/Badge.vue';
import DataTable from '@/components/Kit/Table/DataTable.vue';

defineProps<{ table: InstanceType<typeof DataTable>['$props']['table'] }>();
</script>
<template>
    <DataTable :table="table" row-label="name" />
    <DataTable :table="table" :row-label="row => String(row.email ?? '')" />
    <Badge tone="success">Active</Badge>
    <Badge tone="danger" size="md">Suspended</Badge>
</template>
