<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import { usePendingWork } from '@/components/Kit/Form/pendingWork';
import { useSubmitForm } from '@/components/Kit/Form/submitForm';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A Workbench with `:form` (?workbench-form=submit or =inertia), its title field in the inspector, which starts collapsed. Every save waits in window.kitSave
// until the test answers respond('ok' | '422' | '409'):
// - submit: a useSubmitForm whose handler throws a fetch Response (422 Laravel errors, or the 409 stale_write body the PHP side is checked against);
// - inertia: a stand-in for Inertia v3's useForm (this harness has no Inertia): submit(method, url, options) sets processing, then answers through options'
//   onSuccess, onError (after setting errors, as useForm does) or onHttpException (a 409 HttpResponse whose data is the JSON text).
// window.kitGate holds the Workbench's `can-submit` (a Ref the test flips) and the page's pending-work registry (begin() stands in for an upload in flight).
// The stage holds a contenteditable that binds Mod+S itself (preventDefault, counted in window.kitSave.editorSaves), as an editor with its own save would.
const transport = new URLSearchParams(window.location.search).get('workbench-form') === 'inertia' ? 'inertia' : 'submit';
type Answer = 'ok' | '422' | '409';
const save = ((window as unknown as { kitSave: { calls: { method?: string; url?: string; data: unknown }[]; answers: ((answer: Answer) => void)[]; editorSaves: number; respond: (answer: Answer) => void } }).kitSave = {
    calls: [],
    answers: [],
    editorSaves: 0,
    respond(answer) { this.answers.shift()?.(answer); },
});
const canSubmit = ref(true);
(window as unknown as { kitGate: { canSubmit: typeof canSubmit; begin: () => () => void } }).kitGate = { canSubmit, begin: () => usePendingWork().begin() };
const stale = { status: 409, body: { error: 'stale_write' } };
const errors422 = { title: ['That title is taken.'] };

const submitForm = useSubmitForm({ title: 'Opening' }, (data) => new Promise<void>((resolve, reject) => {
    save.calls.push({ data });
    save.answers.push((answer) => {
        if (answer === 'ok') resolve();
        else if (answer === '422') reject(new Response(JSON.stringify({ errors: errors422 }), { status: 422, headers: { 'content-type': 'application/json' } }));
        else reject(new Response(JSON.stringify(stale.body), { status: stale.status, headers: { 'content-type': 'application/json' } }));
    });
}));

type Options = { onSuccess?: () => void; onError?: (errors: Record<string, string>) => void; onHttpException?: (response: { status: number; data: string; headers: Record<string, string> }) => boolean | void };
const defaults = { title: 'Opening' };
const fake = reactive({
    data: { title: 'Opening' },
    errors: {} as Record<string, string | undefined>,
    processing: false,
    recentlySuccessful: false,
    get isDirty() { return fake.data.title !== defaults.title; },
    get hasErrors() { return Object.values(fake.errors).some(Boolean); },
    progress: null,
    reset() { fake.data.title = defaults.title; },
    clearErrors() { fake.errors = {}; },
    submit(method: string, url: string, options: Options) {
        fake.processing = true;
        fake.errors = {};
        save.calls.push({ method, url, data: { ...fake.data } });
        save.answers.push((answer) => {
            fake.processing = false;
            if (answer === 'ok') { defaults.title = fake.data.title; fake.recentlySuccessful = true; options.onSuccess?.(); }
            else if (answer === '422') { fake.errors = { title: errors422.title[0] }; options.onError?.(fake.errors as Record<string, string>); }
            else options.onHttpException?.({ status: stale.status, data: JSON.stringify(stale.body), headers: { 'content-type': 'application/json' } });
        });
    },
});
const form = transport === 'inertia' ? fake : submitForm;
const title = computed({ get: () => (transport === 'inertia' ? fake.data.title : submitForm.data.title), set: (value: string) => { if (transport === 'inertia') fake.data.title = value; else submitForm.data.title = value; } });
const titleError = computed(() => form.errors.title);
const saveAction: ActionDescriptor = { key: 'save', label: 'Save', url: '/decks/1', method: 'put' };
const actions: ActionDescriptor[] = [{ key: 'preview', label: 'Preview', url: '/decks/1/preview', method: 'post' }];
const items: WorkbenchItem[] = [{ id: 's1', label: 'Opening' }, { id: 's2', label: 'Closing' }];
function editorKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && !event.shiftKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        save.editorSaves++;
    }
}
</script>
<template>
    <main class="p-4">
        <h1 class="text-xl font-semibold">Deck</h1>
        <Workbench :workbench-key="`form-${transport}`" label="Deck" :items="items" current="s1" :actions="actions" :form="form" :save="transport === 'inertia' ? saveAction : null" :default-collapsed="{ inspector: true }" :can-submit="canSubmit">
            <template #stage>
                <div class="grid gap-4 p-4">
                    <p>Slide content</p>
                    <div contenteditable="true" role="textbox" aria-label="Speaker note" aria-multiline="true" class="min-h-16 rounded-md border p-2" data-test-editor @keydown="editorKeydown">Note</div>
                </div>
            </template>
            <template #inspector>
                <div class="grid gap-4 p-2">
                    <Field id="deck-title" label="Title" :error="titleError"><Input id="deck-title" v-model="title" name="title" :invalid="!!titleError" /></Field>
                </div>
            </template>
        </Workbench>
    </main>
</template>
