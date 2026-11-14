<script setup lang="ts">
import ActionGroup from '@/components/Kit/Action/ActionGroup.vue';
import Button from '@/components/Kit/Form/Button.vue';

// ActionGroup (docs/action-group.md), for run.mjs (?action-group), invented labels, read at 1280 and 390px: a column on an auth-sized card (the primary first, a
// secondary Button, and a quiet link whose own `w-auto self-start` must give way to the group's full width), and the default row of three short Buttons.
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-4 text-foreground">
        <h1 class="text-xl font-medium">Action groups</h1>
        <section data-column class="w-full max-w-sm">
            <ActionGroup direction="column">
                <Button type="button" data-action-key="primary">Create account</Button>
                <Button type="button" variant="secondary" data-action-key="secondary">Sign in with a passkey</Button>
                <a href="#back" class="w-auto self-start text-center text-sm text-muted-foreground underline-offset-4 hover:underline" data-action-key="link">Back to sign in</a>
            </ActionGroup>
        </section>
        <section data-row class="w-full">
            <ActionGroup>
                <Button type="button" data-action-key="save">Save</Button>
                <Button type="button" variant="secondary" data-action-key="preview">Preview</Button>
                <Button type="button" variant="secondary" data-action-key="cancel">Cancel</Button>
            </ActionGroup>
        </section>
    </main>
</template>
