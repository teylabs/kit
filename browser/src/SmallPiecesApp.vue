<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import BusyIndicator from '@/components/Kit/Feedback/BusyIndicator.vue';
import { applyFormDefaults } from '@/components/Kit/Form/formDefaults';
import { reveal } from '@/components/Kit/Layout/reveal';
import EmailMessage from '@/components/Kit/Text/EmailMessage.vue';
import CardHeader from '@/components/Kit/Card/CardHeader.vue';

// Small pieces (?small-pieces): EmailMessage, BusyIndicator, reveal() and applyFormDefaults(). window.revealTarget(duration) reveals the far row.
const applied = applyFormDefaults();
const defaultsOnSuccess = (Form as unknown as { props: { setDefaultsOnSuccess: { default: unknown } } }).props.setDefaultsOnSuccess.default;
(window as unknown as { revealTarget: (duration: number) => void }).revealTarget = (duration) => {
    reveal('#far', { duration });
};
</script>
<template>
    <main class="grid max-w-xl gap-4 p-4">
        <h1>Small pieces</h1>
        <p data-case="defaults">{{ applied }} {{ defaultsOnSuccess }}</p>
        <BusyIndicator class="text-primary">Saving…</BusyIndicator>
        <EmailMessage
            title="What came in"
            from="Ana Lopez <ana@example.test>"
            :to="['desk@example.test', 'ops@example.test']"
            subject="New order"
            body="Hello,&#10;Please see attached."
            :attachments="['order.pdf']"
        />
        <EmailMessage id="limited" from="bo@example.test" :body="'word '.repeat(80)" :limit="40" />
        <div style="width: 480px">
            <CardHeader id="long-header" title="2014 Audi S5 Cabriolet 3.0 TFSI 2dr Conv Auto Technik">
                <template #actions><button type="button" data-header-action>Edit</button><button type="button">Archive</button></template>
            </CardHeader>
        </div>
        <div style="height: 2000px" />
        <p id="far">Far row</p>
    </main>
</template>
