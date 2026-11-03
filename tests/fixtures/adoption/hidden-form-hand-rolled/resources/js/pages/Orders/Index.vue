<script setup lang="ts">
import FormLink from '@/UI/Form/FormLink.vue';
</script>
<template>
    <form method="post" action="/orders"><input name="reference"><button type="submit">Save</button></form>
    <FormLink url="/orders/export" />
</template>
