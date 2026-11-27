import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// The Error page is an EmptyState: its message, then a real Button for the recovery (a guest's: Sign in and the home page).
const source = readFileSync(new URL('../../resources/features/workspace-ui/pages/Workspace/Error/Page.vue.stub', import.meta.url), 'utf8');

test('the Error page is an EmptyState with its message and a recovery Button', () => {
    assert.match(source, /import \{ Button, EmptyState, Page \} from '@kit\/ui';/);
    assert.match(source, /<EmptyState [^>]*data-error-state[^>]*>\s*<p role="alert">\{\{ message \}\}<\/p>\s*<template #action>\s*<Button :href="primary.href" data-error-recovery>/);
});

test('the Error page draws its own h1 only outside a shell that already titles it', () => {
    assert.match(source, /const ownHeading = computed\(\(\) => !inShell && !shellTitled\?\.value\);/);
    assert.match(source, /<h1 v-if="ownHeading"[^>]*data-error-heading>\{\{ title \}\}<\/h1>/);
});
