<template>
  <div class="flex flex-col min-h-screen overflow-hidden">
    <HeaderComponent />

    <main class="container mx-auto bg-zinc-800 text-yellow-500 mt-6 p-6 rounded-2xl shadow-2xl flex-grow mb-6 overflow-y-auto">
      <slot></slot>
    </main>

    <FooterComponent />
  </div>
</template>

<script setup lang="ts">
import HeaderComponent from '@/components/Header.vue';
import FooterComponent from '@/components/Footer.vue';
</script>
