<script setup lang="ts">
import { ref } from 'vue';
import { Field, Slider } from '@/components/Kit';
const volume = ref(40);
</script>
<template><form method="post"><Field id="volume" label="Volume"><Slider id="volume" v-model="volume" name="volume" /></Field></form></template>
