<script setup lang="ts">
import Wordmark from '@/pages/Workspace/Partials/Wordmark.vue';

// The signed-in bar's row with the lockup on (?lockup-bar), for run.mjs: the Layout's own header row (Wordmark, then a switcher-sized slot with the Layout's
// classes) given a 120-character product name and workspace name, so the check reads whether the lockup's cap leaves the switcher room to shrink.
const long = 'Northwind Agricultural Cooperative Holdings and Regional Distribution Services for the Greater Valley Area Ltd';
const name = (long + ' ' + long).slice(0, 120);
const product = { name, url: '/', logomark: null, wordmark: null, lockup: true };
</script>
<template>
    <header class="border-b" data-shell-bar>
        <div class="mx-auto flex h-16 min-w-0 items-center gap-2 px-4 sm:px-6 lg:px-8" data-bar-row>
            <Wordmark :product="product" />
            <div class="flex max-w-full min-w-0 flex-1 items-center rounded-md lg:max-w-72 lg:flex-none" data-shell-switcher-group>
                <a href="/w" class="flex min-h-11 min-w-0 items-center px-2 text-sm font-medium" :title="name" data-shell-home><span class="truncate" data-switcher-name>{{ name }}</span></a>
                <button type="button" class="flex min-h-11 min-w-11 shrink-0 items-center justify-center" aria-label="Switch workspace">⌄</button>
            </div>
        </div>
        <nav aria-label="Breadcrumb" class="mx-auto flex min-w-0 px-4" data-crumbs><span class="truncate">{{ name }}</span></nav>
    </header>
</template>
