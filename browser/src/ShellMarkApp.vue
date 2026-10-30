<script setup lang="ts">
import Wordmark from '@/shell/Wordmark.vue';
import FocusShell from '@/shell/FocusShell.vue';

// The shell's mark (?shell-mark) with no --shell-mark-* tokens anywhere: a 1000px logomark in the Wordmark, and a FocusShell whose #mark slot holds the host's own mark.
const logomark = "data:image/svg+xml;utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 10 10'%3E%3Crect width='10' height='10' fill='%23333'/%3E%3C/svg%3E";
const product = { name: 'Product', url: '/', logomark };
const shell = { kind: 'focus', product, step: null, user: { name: 'Ada', email: 'ada@example.test' }, links: { switch_account: '/switch', sign_out: '/logout' } } as never;
</script>
<template>
    <main class="p-4">
        <div data-case="plain" class="bg-shell-bar"><Wordmark :product="product" /></div>
        <div data-case="focus"><FocusShell :shell="shell" :toaster="false"><template #mark><span data-host-mark>H</span></template><p>Welcome</p></FocusShell></div>
    </main>
</template>
