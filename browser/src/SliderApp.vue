<script setup lang="ts">
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import Slider from '@/components/Kit/Form/Slider.vue';

// A Slider in a Field, for run.mjs: ?slider in light, ?slider=dark under .dark. Every input and change event is recorded on window.sliderEvents. Non-modal,
// so axe checks contrast.
if (new URLSearchParams(window.location.search).get('slider') === 'dark') document.documentElement.classList.add('dark');
const volume = ref(40);
const log: string[] = [];
(window as unknown as { sliderEvents: string[] }).sliderEvents = log;
</script>
<template>
    <main class="grid min-h-screen max-w-lg content-start gap-6 bg-background p-4 text-foreground">
        <h1>Settings</h1>
        <Field id="volume" label="Volume" description="How loud alerts play.">
            <Slider id="volume" v-model="volume" name="volume" :step="5" :value-text="v => `${v}%`" show-value data-probe="slider" @input="v => log.push(`input:${v}`)" @change="v => log.push(`change:${v}`)" />
        </Field>
        <p data-volume>{{ volume }}</p>
        <button type="button" data-after>After</button>
    </main>
</template>
