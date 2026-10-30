<template>
  <ul><li v-for="o in orders" :key="o.id">{{ o.total }}</li></ul>
</template>
