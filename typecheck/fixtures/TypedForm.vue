<script setup lang="ts">
// A host's typed useForm<T>() passed to every kit component that takes a form: its reset/clearErrors take `K extends FormDataKeys<T>`, which a
// property-typed `(...fields: string[]) => void` refuses (TS2322). Compiled by bin/typecheck-components.sh; never rendered.
import { useForm } from '@inertiajs/vue3';
import FormActions from '@/components/Kit/Form/FormActions.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';

const form = useForm<{ a: string; b: { c: number } }>({ a: '', b: { c: 1 } });
</script>
<template>
    <FormCard :form="form" title="Typed">
        <FormActions :form="form" />
    </FormCard>
</template>
