<script setup lang="ts">
// Form controls given caller attributes and the new `invalid` / `description` props, as a host would use them. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import FileInput from '@/components/Kit/Form/FileInput.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';
import InputRichText from '@/components/Kit/Form/InputRichText.vue';
import RadioCardGroup from '@/components/Kit/Form/RadioCardGroup.vue';
import Select from '@/components/Kit/Form/Select.vue';
import { emptyDocument, type RichDocument } from '@/components/Kit/Form/richText';

const agreed = ref(false);
const plan = ref<string>();
const vote = ref<string | number | null>(null);
const file = ref<File | null>(null);
const notes = ref<RichDocument | null>(emptyDocument());
const options = [{ value: 'basic', label: 'Basic' }];
</script>
<template>
    <InputCheckbox id="terms" v-model="agreed" invalid required description="You can withdraw at any time." aria-describedby="hint" data-probe="terms">I agree to the <strong>terms</strong></InputCheckbox>
    <Select id="plan" v-model="plan" :options="options" invalid required aria-describedby="hint" />
    <FileInput id="upload" v-model="file" invalid required aria-describedby="hint" />
    <RadioCardGroup id="vote" v-model="vote" :options="options" invalid required aria-describedby="hint" />
    <InputRichText id="notes" v-model="notes" label="Notes" invalid required aria-describedby="hint" />
</template>
