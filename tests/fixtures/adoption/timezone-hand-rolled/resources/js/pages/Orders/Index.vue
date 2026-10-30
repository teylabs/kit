<script setup>
import moment from 'moment-timezone';
const zone = moment.tz.guess();
</script>
<template><p>{{ zone }}</p></template>
