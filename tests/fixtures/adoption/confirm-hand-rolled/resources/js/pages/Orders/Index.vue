<script setup>
import Swal from 'sweetalert2';
const archive = () => Swal.fire({ title: 'Delete this order?', showCancelButton: true });
</script>
<template><a href="#" @click.prevent="archive">Delete</a></template>
