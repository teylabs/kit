<script setup lang="ts">
// RadioCardGroup's option slot, image tiles, disabled options and columns, as a host would use them for a grid of ~100 picture choices. Compiled by
// bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import RadioCardGroup, { type RadioCardOption } from '@/components/Kit/Form/RadioCardGroup.vue';

const avatar = ref<string | number | null>(null);
const plan = ref<string | number | null>(null);
const tiles: RadioCardOption[] = Array.from({ length: 100 }, (_, i) => ({ value: i, image: { src: `/avatars/${i}.png`, alt: `Avatar ${i}` }, disabled: i === 3 }));
</script>
<template>
    <RadioCardGroup id="avatar" v-model="avatar" label="Avatar" columns="auto" :options="tiles" />
    <RadioCardGroup id="plan" v-model="plan" label="Plan" :columns="3" :options="[{ value: 'a', label: 'Basic' }, { value: 'b', label: 'Plus', image: { src: '/plus.png', alt: 'Plus' } }]">
        <template #option="{ option, checked, disabled }">
            <strong>{{ option.label }}</strong>
            <span v-if="checked && !disabled">Selected</span>
        </template>
    </RadioCardGroup>
    <!-- @vue-expect-error columns is 2, 3, 4, 6 or 'auto' -->
    <RadioCardGroup id="bad" v-model="plan" label="Plan" :columns="5" :options="tiles" />
    <!-- @vue-expect-error an option needs a label or an image -->
    <RadioCardGroup id="bad" v-model="plan" label="Plan" :options="[{ value: 'a' }]" />
</template>
