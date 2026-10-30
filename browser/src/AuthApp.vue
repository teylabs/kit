<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import ConfirmPassword from '@/pages/Auth/ConfirmPassword/Page.vue';
import ForgotPassword from '@/pages/Auth/ForgotPassword/Page.vue';
import Login from '@/pages/Auth/Login/Page.vue';
import ResetPassword from '@/pages/Auth/ResetPassword/Page.vue';
import VerifyEmail from '@/pages/Auth/VerifyEmail/Page.vue';

// session-ui's guest pages in a guest card (?auth=Login and so on), over the Inertia <Form> stand-in: the test answers each submit with window.kitRespond.
// `kitUi.form` is the kit's `ui` lang copy, as the server shares it; `status` (?status=) is a view model's flashed status.
const query = new URLSearchParams(window.location.search);
const pages = { Login, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword } as const;
const name = (query.get('auth') ?? 'Login') as keyof typeof pages;
const status = query.get('status');
const props: Record<string, unknown> = { title: name, layout: { title: name }, status, email: name === 'ResetPassword' ? 'ada@example.com' : null, token: 'token', localLogin: null, cancel: '/' };
const page = usePage();
page.props = { ...props, kitUi: { form: { throttled: 'Too many requests. Try again in a moment.', throttled_retry: 'Too many requests. Try again in :seconds seconds.' } } };
</script>
<template>
    <main class="mx-auto max-w-sm p-8">
        <div data-guest-card><component :is="pages[name]" v-bind="props" /></div>
    </main>
</template>
