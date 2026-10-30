<template>
  <header>
    <Link href="/orders">Orders</Link>
    <Link :href="route('logout')" method="post" as="button">Log out</Link>
  </header>
</template>
