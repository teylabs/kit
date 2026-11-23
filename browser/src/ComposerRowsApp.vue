<script setup lang="ts">
import Composer from '@/components/Kit/Form/Composer.vue';

// Two composers, opened (?composer-rows, Hubcommons #1878): the default three lines and `rows=1`, a single-line field with nothing empty below it.
</script>
<template>
    <div class="grid max-w-[40rem] gap-6 p-4">
        <Composer
            id="three"
            open
            placeholder="Three lines (default)"
            data-rows="3"
        />
        <Composer
            id="one"
            open
            :rows="1"
            placeholder="One line"
            data-rows="1"
        />
    </div>
</template>
