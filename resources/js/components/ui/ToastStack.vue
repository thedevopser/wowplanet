<template>
    <ToastProvider :duration="AUTO_CLOSE_MS" label="Notifications" swipe-direction="right">
        <ToastRoot
            v-for="toast in store.items"
            :key="toast.id"
            data-toast
            :type="toast.tone === ERROR ? 'foreground' : 'background'"
            :duration="staysOpen(toast) ? Infinity : AUTO_CLOSE_MS"
            class="flex items-start gap-3 rounded-ui-md border border-l-4 border-default bg-surface p-4 text-default shadow-elevation-2
                data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
            :class="TONES[toast.tone].border"
            @update:open="(open) => !open && store.dismiss(toast.id)"
        >
            <span :class="TONES[toast.tone].text">
                <Icon :icon="TONES[toast.tone].icon" />
            </span>
            <div class="min-w-0 flex-1">
                <ToastTitle class="font-semibold">{{ toast.title }}</ToastTitle>
                <ToastDescription v-if="toast.description" class="mt-1 text-sm text-muted">{{ toast.description }}</ToastDescription>
                <ToastAction v-if="toast.action" as-child :alt-text="toast.action.label">
                    <a
                        :href="toast.action.href"
                        class="mt-3 inline-flex h-9 items-center rounded-ui-md border border-strong bg-surface-raised px-3 text-sm font-medium text-default
                            hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >{{ toast.action.label }}</a>
                </ToastAction>
            </div>
            <ToastClose as-child>
                <IconButton :icon="X" label="Fermer la notification" icon-size="sm" class="-mr-2 -mt-2" />
            </ToastClose>
        </ToastRoot>
        <ToastViewport
            data-toast-viewport
            class="fixed inset-x-4 z-toast flex flex-col gap-2 outline-none sm:left-auto sm:w-full sm:max-w-sm"
            :class="OFFSETS[offset] ?? OFFSETS.default"
        />
    </ToastProvider>
</template>

<script>
import { CircleAlert, CircleCheck, CircleX, Info } from 'lucide-vue-next';

const ERROR = 'error';

const TONES = Object.freeze({
    info: { icon: Info, text: 'text-info', border: 'border-l-info' },
    success: { icon: CircleCheck, text: 'text-success', border: 'border-l-success' },
    warning: { icon: CircleAlert, text: 'text-warning', border: 'border-l-warning' },
    [ERROR]: { icon: CircleX, text: 'text-danger', border: 'border-l-danger' },
});

// above-fab keeps the stack clear of the floating tasks button.
const OFFSETS = Object.freeze({
    default: 'bottom-4',
    'above-fab': 'bottom-24',
});
</script>

<script setup>
import { ToastAction, ToastClose, ToastDescription, ToastProvider, ToastRoot, ToastTitle, ToastViewport } from 'reka-ui';
import { X } from 'lucide-vue-next';
import Icon from './Icon.vue';
import IconButton from './IconButton.vue';
import { useToastStore } from '../../stores/toasts';

const AUTO_CLOSE_MS = 5000;

defineProps({
    offset: { type: String, default: 'default', validator: (value) => Object.hasOwn(OFFSETS, value) },
});

const store = useToastStore();

// An error, or a notification waiting for an action, must not vanish before being read.
const staysOpen = (toast) => toast.tone === ERROR || Boolean(toast.action);
</script>
