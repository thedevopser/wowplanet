<template>
  <div class="relative flex items-center p-2 cursor-pointer rounded-lg hover:bg-zinc-800 transition">
    <!-- Avatar utilisateur -->
    <img
        :src="user.avatar || defaultAvatar"
        :alt="user.name"
        class="w-12 h-12 rounded-full border-2 border-yellow-500"
        @click="toggleMenu"
    />

    <!-- Nom de l'utilisateur -->
    <span class="text-yellow-500 font-semibold ml-6" @click="toggleMenu">
      {{ user.name }}
    </span>

    <!-- Menu déroulant (Affiché sous l'avatar et le nom) -->
    <div
        v-if="isMenuOpen"
        class="absolute left-0 top-full mt-3 w-40 bg-zinc-900 border border-zinc-800 shadow-md rounded-lg z-50"
    >
      <ul class="text-yellow-500">
        <li class="px-4 py-2 hover:bg-zinc-800 cursor-pointer" @click="logout">
          Se déconnecter
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, defineProps } from 'vue';
import type { User } from '@/types/User';
import defaultAvatar from '@/assets/logo.webp';

defineProps<{ user: User }>();

const isMenuOpen = ref(false);

const toggleMenu = () => {
  isMenuOpen.value = !isMenuOpen.value;
};

const logout = () => {
  console.log("Déconnexion...");
};
</script>
