<script setup lang="ts">
import { nextTick, ref } from 'vue';
import FormLink from '@/components/Kit/Form/FormLink.vue';
import { useNewTabForm } from '@/components/Kit/Form/useNewTabForm';

// FormLink and useNewTabForm inside a stand-in modal (?form-link). Each control closes the modal as it fires, as @inertiaui/modal unmounts its content:
// the hidden form still goes out, because it lives on <body>, not in the modal. The test routes the requests and reads what the browser sent.
const open = ref(true);
const { open: validateThenOpen } = useNewTabForm({ period: '2026-10' });
// The validating POST "succeeds" after the modal has gone, as a back() redirect tears it down before onSuccess.
(window as unknown as { kitFormPost: (url: string, options: { onSuccess: (page: unknown) => void }) => void }).kitFormPost = async (_url, options) => {
    open.value = false;
    await nextTick();
    options.onSuccess({ props: { export: 'e-17' } });
};
</script>
<template>
    <div v-if="open" role="dialog" data-test-modal>
        <FormLink href="/exports/download?format=csv" :data="{ ids: [3, 4], filters: { open: true, owner: null } }" data-test-get @submitted="open = false">Download CSV</FormLink>
        <FormLink href="/exports" method="delete" :data="{ id: 9 }" data-test-delete @submitted="open = false">Delete export</FormLink>
        <button type="button" data-test-validate @click="validateThenOpen('/exports/validate', (page) => `/exports/${(page as { props: { export: string } }).props.export}`, { target: '_self', data: { as: 'pdf' } })">Validate and open</button>
    </div>
    <p v-else data-test-closed>Closed</p>
</template>
