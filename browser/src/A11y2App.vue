<script setup lang="ts">
import { ref } from 'vue';
import ActivityStream from '@/components/Kit/Feed/ActivityStream.vue';
import StageNotice from '@/components/Kit/Feedback/StageNotice.vue';
import LockedForm from '@/components/Kit/Form/LockedForm.vue';
import RadioCardGroup from '@/components/Kit/Form/RadioCardGroup.vue';
import Slider from '@/components/Kit/Form/Slider.vue';
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';
import MediaCollection from '@/components/Kit/Media/MediaCollection.vue';

// More rendered accessibility contracts, for run.mjs (?a11y2): RadioCardGroup in click mode, a Slider in a locked form, StageNotice, an empty ActivityStream,
// MediaCollection's item menus, and FloatingToolbar's icon-only items.
const vote = ref<string | number | null>(null);
const commits = ref<(string | number)[]>([]);
const volume = ref(40);
const media = { collection: 'photos', version: '1', items: [1, 2].map(n => ({ id: `p${n}`, name: `photo-${n}.jpg`, mime: 'image/jpeg', size: 1000, src: `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"/>')}`, srcset: '', width: 8, height: 8 })) };
const moved = ref<string[]>([]);
const tools = [{ key: 'bold', label: 'Bold', icon: 'i-bold', iconOnly: true, group: 'text' }, { key: 'link', label: 'Link', group: 'insert' }];
const used = ref<string[]>([]);
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>More</h1>
        <section data-vote><RadioCardGroup id="vote" v-model="vote" label="Vote" commit="click" :options="[{ value: 'a', label: 'Alpha' }, { value: 'b', label: 'Beta' }, { value: 'c', label: 'Gamma' }]" @commit="v => commits.push(v)" /></section>
        <p data-commits>{{ commits.join(',') }}</p>
        <section data-locked><LockedForm :save="null"><Slider id="volume" v-model="volume" aria-label="Volume" /></LockedForm></section>
        <p data-volume>{{ volume }}</p>
        <section data-stage class="relative h-40 [container-type:size]"><StageNotice>Recording</StageNotice></section>
        <section data-stage-quiet class="relative h-40 [container-type:size]"><StageNotice live="off">Quiet</StageNotice></section>
        <section data-activity><ActivityStream :entries="[]" empty="No activity on this case." /></section>
        <section data-media><MediaCollection :collection="media" label="Photos" :accept="['image/jpeg']" :max-bytes="1000000" :move-targets="[{ collection: 'floorplans', label: 'Floor plans' }]" @move="(item, to) => moved.push(`${item.id}>${to}`)" @remove="item => moved.push(`${item.id}x`)" /></section>
        <p data-moved>{{ moved.join(',') }}</p>
        <section data-tools><FloatingToolbar mode="inline" label="Formatting" :items="tools" :on-action="item => used.push(item.key)" /></section>
        <p data-used>{{ used.join(',') }}</p>
    </main>
</template>
