<script setup lang="ts">
// FormStatus with each prop: the flashed status by default, a message (or null for none), each tone, and a useForm()-like form. Compiled by bin/typecheck-components.sh; never rendered.
import FormStatus from '@/components/Kit/Form/FormStatus.vue';

const form = { processing: false };
</script>
<template>
    <FormStatus />
    <FormStatus message="You're on the list." tone="success" :form="form" />
    <FormStatus :message="null" tone="info" :form="null" />
    <FormStatus message="Check your spam folder too." tone="warning" class="mt-4" data-waitlist-status />
</template>
