<script setup lang="ts">
// RadioCardGroup's v-model, EmojiTray's react event, Button's lg and xl sizes and its link forms (Inertia, a non-GET visit, external), as a host would use them. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import EmojiTray from '@/components/Kit/Form/EmojiTray.vue';
import type { EmojiReaction } from '@/components/Kit/Form/emojiTray';
import Field from '@/components/Kit/Form/Field.vue';
import RadioCardGroup from '@/components/Kit/Form/RadioCardGroup.vue';

const choice = ref<string | number | null>(null);
const last = ref<EmojiReaction | null>(null);
</script>
<template>
    <Field id="poll" label="Your answer">
        <RadioCardGroup id="poll" v-model="choice" name="answer" :options="[{ value: 'a', label: 'Yes', result: 60 }, { value: 'b', label: 'No', disabled: true }]" show-results />
    </Field>
    <EmojiTray :throttle="500" @react="reaction => (last = reaction)" />
    <Button size="lg">Next</Button>
    <Button size="xl" variant="secondary">Previous</Button>
    <Button href="/decks/1" size="lg" preserve-scroll>Open</Button>
    <Button href="/decks/1/publish" method="post" :data="{ notify: true, slides: [1, 2] }" variant="secondary">Publish</Button>
    <Button href="https://example.com/help" external target="_blank" variant="link">Help</Button>
</template>
