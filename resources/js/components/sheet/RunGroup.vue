<template>
    <details class="group">
        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1 text-sm font-medium text-muted hover:text-default">
            <Icon :icon="ChevronRight" size="sm" class="transition-transform duration-fast group-open:rotate-90" />
            Composition du groupe
        </summary>
        <ul class="pb-3 text-sm">
            <li v-for="(member, index) in members" :key="index" class="flex items-center justify-between gap-2 border-b border-default py-1.5 last:border-0">
                <span class="min-w-0 truncate">
                    <span class="font-medium text-default">{{ member.name }}</span>
                    <span class="ml-2 hidden text-subtle sm:inline">{{ member.realm }}</span>
                </span>
                <span class="shrink-0 text-muted">{{ member.spec }} · <span class="tabular-nums">{{ member.ilvl }}</span></span>
            </li>
        </ul>
    </details>
</template>

<script setup>
import { ChevronRight } from 'lucide-vue-next';
import Icon from '../ui/Icon.vue';

defineProps({
    members: { type: Array, required: true },
});
</script>
