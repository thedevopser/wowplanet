<template>
    <div class="flex flex-col items-center gap-3 rounded-ui-md border border-default bg-surface px-6 py-10 text-center">
        <span class="flex size-12 items-center justify-center rounded-full bg-surface-raised text-subtle">
            <Icon :icon="icon" size="lg" />
        </span>
        <p class="font-semibold text-default">{{ title }}</p>
        <p v-if="message" class="max-w-prose text-sm text-muted">{{ message }}</p>
        <div v-if="$slots.action" data-empty-action class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>

<script setup>
import { Inbox } from 'lucide-vue-next';
import Icon from './Icon.vue';

defineProps({
    title: { type: String, required: true },
    message: { type: String, default: '' },
    icon: { type: [Object, Function], default: () => Inbox },
});
</script>
