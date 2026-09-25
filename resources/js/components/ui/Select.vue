<template>
    <div class="flex flex-wrap items-center gap-2">
        <SelectRoot v-model="model">
            <label :id="labelId" class="text-sm font-medium text-muted">{{ label }}</label>
            <SelectTrigger
                :aria-labelledby="labelId"
                class="inline-flex h-11 min-w-64 items-center justify-between gap-3 rounded-ui-md border border-strong bg-surface px-3 text-sm text-default
                    transition-colors duration-fast hover:bg-surface-raised
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                <span class="flex min-w-0 items-baseline gap-2">
                    <SelectValue class="truncate font-medium" />
                    <span v-if="current?.hint" class="shrink-0 text-xs tabular-nums text-subtle">{{ current.hint }}</span>
                </span>
                <SelectIcon as-child>
                    <Icon :icon="ChevronsUpDown" size="sm" class="shrink-0 text-subtle" />
                </SelectIcon>
            </SelectTrigger>
            <SelectPortal>
                <SelectContent
                    position="popper"
                    :side-offset="4"
                    class="z-overlay max-h-(--reka-select-content-available-height) min-w-(--reka-select-trigger-width) overflow-hidden rounded-ui-md border border-default bg-surface
                        text-default shadow-elevation-1 data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
                >
                    <SelectViewport class="p-1">
                        <SelectItem
                            v-for="option in options"
                            :key="option.value"
                            :value="option.value"
                            class="relative flex min-h-11 cursor-pointer flex-col justify-center gap-1 rounded-ui-sm py-2 pl-8 pr-3 text-sm outline-none
                                data-highlighted:bg-surface-raised data-[state=checked]:font-semibold"
                        >
                            <SelectItemIndicator class="absolute left-2 top-1/2 -translate-y-1/2 text-accent">
                                <Icon :icon="Check" size="sm" />
                            </SelectItemIndicator>
                            <span class="flex items-baseline justify-between gap-4">
                                <SelectItemText data-option-label>{{ option.label }}</SelectItemText>
                                <span v-if="option.hint" class="shrink-0 text-xs font-normal tabular-nums text-subtle">{{ option.hint }}</span>
                            </span>
                            <span v-if="option.progress" aria-hidden="true" class="block h-1 overflow-hidden rounded-full bg-surface-raised">
                                <span
                                    data-option-progress
                                    class="block h-full rounded-full"
                                    :class="{ 'bg-accent': !option.progress.color }"
                                    :style="{ width: `${percentOf(option.progress)}%`, backgroundColor: option.progress.color }"
                                />
                            </span>
                        </SelectItem>
                    </SelectViewport>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>
    </div>
</template>

<script>
const isValidOption = (option) => typeof option?.value === 'string' && typeof option.label === 'string';
</script>

<script setup>
import { computed, useId } from 'vue';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import {
    SelectContent,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import Icon from './Icon.vue';

const props = defineProps({
    label: { type: String, required: true },
    options: { type: Array, required: true, validator: (options) => options.every(isValidOption) },
});

const model = defineModel({ type: String, required: true });

const labelId = useId();

const current = computed(() => props.options.find((option) => option.value === model.value));

const percentOf = ({ value, max }) => (max > 0 ? Math.min(100, (value / max) * 100) : 0);
</script>
