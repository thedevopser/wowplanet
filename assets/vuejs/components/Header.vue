<template>
  <header class="bg-zinc-900 text-yellow-500 py-4 shadow-md">
    <div class="container mx-auto flex justify-between items-center px-4">
      <div class="flex items-center gap-3">
        <img src="@/assets/logo.webp" alt="Logo Wowplanet" class="w-12 h-12 rounded-full" />
        <span class="text-2xl font-bold">Wowplanet</span>
      </div>

      <nav v-if="user" class="flex gap-4">
        <slot name="menu">
          <a href="#" class="text-yellow-500 hover:text-yellow-400">Lien 1</a>
          <a href="#" class="text-yellow-500 hover:text-yellow-400">Lien 2</a>
          <a href="#" class="text-yellow-500 hover:text-yellow-400">Lien 3</a>
        </slot>
      </nav>

      <UserComponent v-if="user" :user="user" />
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import UserComponent from '@/components/User.vue';
import type { User } from '@/types/User';

const user = ref<User | null>({
  id: 1,
  name: "John Doe",
  email: "johndoe@example.com",
  avatar: ""
});
</script>
