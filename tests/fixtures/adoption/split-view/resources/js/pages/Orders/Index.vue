<template>
  <div class="grid grid-cols-2">
    <OrderList :orders="orders" />
    <OrderDetail :order="selected" />
  </div>
</template>
