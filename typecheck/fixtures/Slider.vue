<script setup lang="ts">
// Slider's v-model, range, valueText, events and Field use, as a host would use it. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import Slider from '@/components/Kit/Form/Slider.vue';

const volume = ref(40);
const saved = ref<number[]>([]);
</script>
<template>
    <Field id="volume" label="Volume" description="How loud alerts play.">
        <Slider id="volume" v-model="volume" name="volume" :min="0" :max="100" :step="5" :value-text="v => `${v}%`" show-value class="w-64" @input="v => (volume = v)" @change="v => saved.push(v)" />
    </Field>
    <Slider v-model="volume" disabled invalid aria-label="Balance" />
    <!-- @vue-expect-error valueText takes a number and returns a string -->
    <Slider v-model="volume" aria-label="Balance" :value-text="(v: string) => v" />
</template>
