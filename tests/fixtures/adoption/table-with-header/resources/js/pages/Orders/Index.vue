<template>
  <table>
    <thead><tr><th>Order</th></tr></thead>
    <tbody><tr v-for="o in orders" :key="o.id"><td>{{ o.id }}</td></tr></tbody>
  </table>
</template>
