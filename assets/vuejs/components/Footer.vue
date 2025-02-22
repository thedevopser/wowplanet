<template>
  <footer class="bg-zinc-900 text-yellow-500 py-4 shadow-md">
    <div class="container mx-auto flex flex-col md:flex-row justify-between items-center px-4">
      <div class="mb-2 md:mb-0">
        &copy; {{ currentYear }} TheDevOpser
      </div>

      <div class="mb-2 md:mb-0">
        <a href="/cgu" class="text-yellow-500 hover:text-yellow-400 mx-2">CGU</a>
        <a href="/confidentialite" class="text-yellow-500 hover:text-yellow-400 mx-2">Confidentialité</a>
      </div>

      <div class="flex space-x-4">
        <a href="https://gitlab.com/thedevopser" target="_blank" aria-label="GitLab" class="text-yellow-500 hover:text-yellow-400">
          <font-awesome-icon :icon="['fab', 'gitlab']" size="lg" />
        </a>
        <a href="https://github.com/thedevopser" target="_blank" aria-label="GitHub" class="text-yellow-500 hover:text-yellow-400">
          <font-awesome-icon :icon="['fab', 'github']" size="lg" />
        </a>
        <a href="https://discord.gg/VduGmxXfRC" target="_blank" aria-label="Discord" class="text-yellow-500 hover:text-yellow-400">
          <font-awesome-icon :icon="['fab', 'discord']" size="lg" />
        </a>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">

const currentYear = new Date().getFullYear();
</script>

<style scoped>
</style>
