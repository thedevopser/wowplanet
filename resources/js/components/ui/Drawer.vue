<template>
    <DialogRoot v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogPortal>
            <DialogOverlay
                data-drawer-overlay
                class="fixed inset-0 z-overlay bg-night-950/60 data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
            />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed inset-y-0 z-dialog flex w-full max-w-xs flex-col overflow-y-auto border-default bg-surface p-4 text-default shadow-elevation-2
                    data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
                :class="SIDES[side]"
            >
                <div class="flex items-center justify-between gap-4">
                    <DialogTitle class="text-base font-semibold text-default">{{ title }}</DialogTitle>
                    <DialogClose as-child>
                        <IconButton :icon="X" :label="closeLabel" class="-mr-2" />
                    </DialogClose>
                </div>
                <div class="mt-4 flex-1">
                    <slot />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<script>
const SIDES = Object.freeze({
    left: 'left-0 border-r',
    right: 'right-0 border-l',
});
</script>

<script setup>
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, DialogTrigger } from 'reka-ui';
import { X } from 'lucide-vue-next';
import IconButton from './IconButton.vue';

defineProps({
    title: { type: String, required: true },
    side: { type: String, default: 'right', validator: (value) => Object.hasOwn(SIDES, value) },
    closeLabel: { type: String, default: 'Fermer le menu' },
});

const open = defineModel('open', { type: Boolean, default: false });
</script>
