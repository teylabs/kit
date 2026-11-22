<script setup lang="ts">
import { ModalLink } from '@inertiaui/modal-vue';
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';

// The page behind the modal (select-modal.html): an Input beside a one-value InputSelect on a white card over a grey ground (a tinted --background, as on a
// kit app's page), read for height, fill and chevron inset at each width, a clearable one, a multiple one with one chip and a narrow one whose chips wrap;
// the ModalLink opens Select/Modal.
const rooms = ['North', 'South', 'East', 'West', 'Attic', 'Cellar', 'Garden', 'Library'].map((label) => ({ value: label.toLowerCase(), label }));
const room = ref<string | null>('north');
const cleared = ref<string | null>('south');
const many = ref<string[]>(['north']);
const wrapped = ref<string[]>(rooms.map((room) => room.value));
const name = ref('Ada');
</script>
<template>
    <div class="flex flex-col gap-4">
        <div class="bg-background p-3 [--background:hsl(0_0%_96%)] dark:[--background:hsl(0_0%_3.9%)]">
            <div data-test-card class="bg-card grid grid-cols-2 items-start gap-4 rounded-lg border p-4 *:min-w-0">
                <Field id="base-name" label="Name"><Input id="base-name" v-model="name" name="name" /></Field>
                <Field id="base-room" label="Room"><InputSelect id="base-room" v-model="room" name="room" :options="rooms" /></Field>
            </div>
        </div>
        <Field id="base-clearable" label="Backup room"><InputSelect id="base-clearable" v-model="cleared" :options="rooms" clearable /></Field>
        <Field id="base-many" label="Rooms"><InputSelect id="base-many" v-model="many" :options="rooms" multiple /></Field>
        <div class="w-60"><Field id="base-wrapped" label="Every room"><InputSelect id="base-wrapped" v-model="wrapped" :options="rooms" multiple /></Field></div>
        <ModalLink href="/@acme/bookings/new">New booking</ModalLink>
    </div>
</template>
