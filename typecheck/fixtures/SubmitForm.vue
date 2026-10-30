<script setup lang="ts">
// useSubmitForm and the form frame, typed as a host would write them: a typed useSubmitForm<T>() keeps its field keys (data, errors, reset), the handler must
// return a Promise, and FormCard, FormActions and Workbench take it as :form. A Workbench given a useForm() takes `save`, an action descriptor whose url and
// method are required. Compiled by bin/typecheck-components.sh; never rendered.
import { useForm, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormActions from '@/components/Kit/Form/FormActions.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';
import { SubmitError, httpSubmit, submitBlock, useSubmitForm, type SubmitBlock } from '@/components/Kit/Form/submitForm';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

type Post = { title: string; body: string; tags: string[] };
const form = useSubmitForm<Post>({ title: '', body: '', tags: [] }, async (data) => {
    const title: string = data.title;
    if (!title) throw new SubmitError({ title: 'Required.' });
    await fetch('/posts', { method: 'POST', body: JSON.stringify(data) });
}, { timeout: 5000, resetOnSuccess: ['body'], warnOnLeave: false });
const title: string = form.data.title;
const titleError: string | undefined = form.errors.title;
const formError: string | undefined = form.errors._form;
form.reset('title', 'tags');
form.clearErrors('_form');
form.setDefaults({ title: 'Saved' });
const done: Promise<boolean> = form.submit();
// @ts-expect-error `nope` is not a field of Post
void form.errors.nope;
// @ts-expect-error resetOnSuccess names fields of Post only
useSubmitForm<Post>({ title: '', body: '', tags: [] }, async () => {}, { resetOnSuccess: ['nope'] });
// @ts-expect-error the handler must return a Promise (an async function, or one that returns the request)
useSubmitForm({ title: '' }, () => undefined);
// transform shapes what the handler receives.
useSubmitForm({ first: 'Ada', last: 'Lovelace' }, async (payload: { name: string }) => payload.name, { transform: (data) => ({ name: `${data.first} ${data.last}` }) });
// httpSubmit is a handler useSubmitForm takes as is, and its hooks are valid useHttp options (spread with others too).
const http = useHttp<{ title: string }>({ title: '' });
useSubmitForm({ title: '' }, httpSubmit((data: { title: string }, hooks) => http.transform(() => data).post('/posts', hooks)));
useSubmitForm({ title: '' }, httpSubmit((data: { title: string }, hooks) => http.transform(() => data).put('/posts/1', { ...hooks, headers: { 'X-Version': '3' } })));

// canSubmit: a boolean, a Ref or a getter, on the form and on the frames.
const ready = ref(true);
const gated = useSubmitForm({ title: '' }, async () => {}, { canSubmit: ready });
useSubmitForm({ title: '' }, async () => {}, { canSubmit: () => ready.value });
const canNow: boolean = gated.canSubmit;
const blocked: SubmitBlock | null = submitBlock({ pending: false, conflict: false, canSubmit: canNow });
// @ts-expect-error canSubmit is a boolean, not a string
useSubmitForm({ title: '' }, async () => {}, { canSubmit: 'yes' });

const inertia = useForm<{ title: string }>({ title: '' });
const save: ActionDescriptor = { label: 'Save', url: '/slides/1', method: 'put', key: 'save' };
// @ts-expect-error a save descriptor needs its url
const noUrl: ActionDescriptor = { label: 'Save', method: 'put' };
// @ts-expect-error a save descriptor needs its method
const noMethod: ActionDescriptor = { label: 'Save', url: '/slides/1' };
void [title, titleError, formError, done, noUrl, noMethod, blocked];
</script>
<template>
    <FormCard :form="form" title="Post" />
    <FormActions :form="form" />
    <Workbench workbench-key="post" label="Post" :form="form" />
    <Workbench workbench-key="slides" label="Slides" :form="inertia" :save="save" save-label="Save slides" />
    <FormActions :form="form" :can-submit="ready" />
    <Workbench workbench-key="gated" label="Gated" :form="gated" :can-submit="() => ready" />
    <Workbench workbench-key="custom" label="Custom" :form="form" :warn-on-leave="false" @submit="() => form.submit()" />
</template>
