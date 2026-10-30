<script setup lang="ts">
import { ref } from 'vue';
import FileInput from '@/components/Kit/Form/FileInput.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';
import InputNumber from '@/components/Kit/Form/InputNumber.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import RadioCardGroup from '@/components/Kit/Form/RadioCardGroup.vue';
import Select from '@/components/Kit/Form/Select.vue';
import TimezoneSelect from '@/components/Kit/Form/TimezoneSelect.vue';

// Each kit form control given a caller's aria-describedby, aria-invalid (through `invalid`) and required, with its hint in the page: the test reads them off
// the focusable control itself, not a wrapper. ?form-attrs
const agreed = ref(false);
const plan = ref<string>();
const choice = ref<string | number | null>(null);
const pick = ref<string | number | null>(null);
const zone = ref('UTC');
const file = ref<File | null>(null);
const options = [
    { value: 'basic', label: 'Basic' },
    { value: 'plus', label: 'Plus' },
];
</script>
<template>
    <main class="grid max-w-lg gap-6 p-8">
        <h1 class="text-lg font-medium">Form attributes</h1>
        <p id="hint" class="text-sm">Required to continue.</p>

        <InputCheckbox id="terms" v-model="agreed" invalid required description="You can withdraw at any time." aria-describedby="hint" class="mt-2" data-probe="checkbox">I agree to the <strong>terms</strong></InputCheckbox>

        <div class="grid gap-2">
            <label id="plan-label" for="plan" class="text-sm">Plan</label>
            <Select id="plan" v-model="plan" :options="options" invalid required aria-describedby="hint" data-probe="select" />
        </div>

        <div class="grid gap-2">
            <label for="upload" class="text-sm">Upload</label>
            <FileInput id="upload" v-model="file" invalid required aria-describedby="hint" data-probe="file" />
        </div>

        <div class="grid gap-2">
            <p id="vote-label" class="text-sm">Vote</p>
            <RadioCardGroup id="vote" v-model="choice" name="vote" :options="options" invalid required aria-describedby="hint" data-probe="radio" />
        </div>

        <div class="grid gap-2">
            <label for="fruit" class="text-sm">Fruit</label>
            <InputSelect id="fruit" v-model="pick" :options="options" invalid required aria-describedby="hint" data-probe="input-select" />
        </div>

        <div class="grid gap-2">
            <label for="zone" class="text-sm">Timezone</label>
            <TimezoneSelect id="zone" v-model="zone" invalid required aria-describedby="hint" data-probe="timezone" />
        </div>

        <InputNumber id="qty" name="qty" label="Quantity" error="Enter a number." required aria-describedby="hint" data-probe="number" />
    </main>
</template>
