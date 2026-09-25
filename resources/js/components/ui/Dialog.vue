<template>
    <DialogRoot v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogPortal>
            <DialogOverlay
                data-dialog-overlay
                class="fixed inset-0 z-overlay bg-night-950/60 data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
            />
            <DialogContent
                v-bind="description ? {} : WITHOUT_DESCRIPTION"
                :class="SIZES[size]"
                class="fixed inset-x-4 top-1/2 z-dialog mx-auto max-h-full -translate-y-1/2 overflow-y-auto rounded-ui-md border border-default bg-surface p-6 text-default shadow-elevation-2
                    data-[state=open]:animate-dialog-in data-[state=closed]:animate-dialog-out"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <DialogTitle class="text-lg font-semibold text-default">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-sm text-muted">{{ description }}</DialogDescription>
                    </div>
                    <DialogClose as-child>
                        <IconButton :icon="X" label="Fermer" class="-mr-2 -mt-2" />
                    </DialogClose>
                </div>
                <div class="mt-4">
                    <slot />
                </div>
                <div v-if="$slots.footer" data-dialog-footer class="mt-6 flex flex-wrap justify-end gap-3">
                    <slot name="footer" />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<script setup>
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    DialogTrigger,
} from 'reka-ui';
import { X } from 'lucide-vue-next';
import IconButton from './IconButton.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    size: { type: String, default: 'reading', validator: (value) => ['reading', 'wide'].includes(value) },
});

const SIZES = Object.freeze({ reading: 'max-w-lg', wide: 'max-w-3xl' });

// Reka warns about a dialog without description unless told explicitly there is none.
const WITHOUT_DESCRIPTION = Object.freeze({ 'aria-describedby': undefined });

const open = defineModel('open', { type: Boolean, default: false });
</script>
