<script setup lang="ts">
import { ref } from 'vue';
import Checklist from '@/components/Kit/Feedback/Checklist.vue';
import FinalizeControl from '@/components/Kit/Feedback/FinalizeControl.vue';
import FinalizedBanner from '@/components/Kit/Feedback/FinalizedBanner.vue';
import type { ChecklistItem } from '@/components/Kit/Feedback/checklist';

// A finalize gate as the server sends it (?finalize): a check with an error and a fix link, a confirmation with its Confirm descriptor, an optional
// skipped step, and the server's progress, which says incomplete although no step failed. Then the certify control (disabled with the server's reason
// in one copy, enabled in the other) and the finalized banner with an Un-finalize that asks for a reason. Nothing posts: the URLs go nowhere.
const steps = ref<ChecklistItem[]>([
    { key: 'amount', label: 'Amount entered', state: 'done', required: true },
    {
        key: 'comparables',
        label: 'Comparables adjusted',
        state: 'active',
        required: true,
        errors: [
            {
                message: 'Comparable 2 has no adjusted price.',
                field: 'comparables.1.price',
                url: '#comparables',
            },
            { message: 'Comparable 3 has no sale date.' },
        ],
        actions: [
            {
                key: 'fix-comparables',
                label: 'Open comparables',
                url: '#comparables',
                method: 'get',
            },
        ],
    },
    {
        key: 'review',
        label: 'Printed figures reviewed',
        state: 'pending',
        required: true,
        reason: 'Changed since Ada confirmed it on Nov 5, 2026.',
        errors: [{ message: 'Changed since Ada confirmed it on Nov 5, 2026.' }],
        actions: [
            {
                key: 'confirm-review',
                label: 'Confirm',
                url: '#confirm',
                method: 'post',
            },
        ],
    },
    {
        key: 'details',
        label: 'Details filled in',
        state: 'pending',
        required: true,
        action: { label: 'Go to Details', url: '#details', name: 'details' },
    },
    {
        key: 'photos',
        label: 'Photos attached',
        state: 'skipped',
        required: false,
        reason: 'This report has no photos.',
    },
]);
const statement =
    'I certify that, to the best of my knowledge, the figures in this report are true and correct.\n(Placeholder until the client supplies the wording.)';
</script>
<template>
    <main class="mx-auto grid max-w-2xl gap-8 p-6">
        <section data-test-gate>
            <Checklist
                :items="steps"
                label="Finalize"
                active="turn"
                :progress="{
                    complete: false,
                    currentKey: 'comparables',
                    revision: 'r1',
                }"
            >
                <template #actions="{ complete }">
                    <span data-test-complete>{{
                        complete ? 'complete' : 'incomplete'
                    }}</span>
                </template>
            </Checklist>
        </section>
        <section data-test-blocked>
            <FinalizeControl
                :statement="statement"
                :action="{
                    key: 'finalize',
                    label: 'Certify and finalize',
                    url: '#finalize',
                    method: 'post',
                    disabled: true,
                    reason: 'Every required step must be done first.',
                }"
            />
        </section>
        <section data-test-ready>
            <FinalizeControl
                :statement="statement"
                :action="{
                    key: 'finalize',
                    label: 'Certify and finalize',
                    url: '#finalize',
                    method: 'post',
                }"
            />
        </section>
        <section data-test-banner>
            <FinalizedBanner
                :finalization="{
                    by: 'Ada Lovelace',
                    at: '2026-11-05T14:30:00+00:00',
                    channel: 'mcp',
                    unfinalize: {
                        key: 'unfinalize',
                        label: 'Un-finalize',
                        url: '#unfinalize',
                        method: 'post',
                    },
                }"
            />
        </section>
        <section data-test-banner-plain>
            <FinalizedBanner
                :finalization="{
                    by: 'Grace Hopper',
                    at: '2026-11-05T14:30:00+00:00',
                    channel: 'web',
                    unfinalize: null,
                }"
            />
        </section>
    </main>
</template>
