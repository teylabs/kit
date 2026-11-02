<script setup>
import { startAuthentication } from '@simplewebauthn/browser';
</script>
<template><button type="button" @click="startAuthentication({})">Use a passkey</button></template>
