<template>
    <div role="alert" class="flex flex-col items-center gap-3 rounded-ui-md border border-danger/40 bg-danger/10 px-6 py-8 text-center">
        <span class="text-danger">
            <Icon :icon="CircleAlert" size="lg" />
        </span>
        <p class="font-semibold text-default">{{ title }}</p>
        <p class="max-w-prose text-sm text-muted">{{ message }}</p>
        <Button v-if="onRetry" class="mt-2" @click="onRetry">
            <Icon :icon="RotateCw" size="sm" />
            Réessayer
        </Button>
    </div>
</template>

<script setup>
import { CircleAlert, RotateCw } from 'lucide-vue-next';
import Button from './Button.vue';
import Icon from './Icon.vue';

// onRetry is declared as a prop so that the retry button shows only when @retry is bound.
defineProps({
    message: { type: String, required: true },
    title: { type: String, default: 'Une erreur est survenue' },
    onRetry: { type: Function, default: null },
});
</script>
