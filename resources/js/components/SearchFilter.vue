<template>
    <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <label :for="inputId" class="sr-only">{{ placeholder }}</label>
            <Icon :icon="Search" size="sm" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-subtle" />
            <input
                :id="inputId"
                type="search"
                :value="search"
                :placeholder="placeholder"
                class="h-11 w-full rounded-ui-md border border-strong bg-surface pl-10 pr-12 text-sm text-default placeholder:text-subtle
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent [&::-webkit-search-cancel-button]:hidden"
                @input="onInput"
            >
            <IconButton v-if="search" :icon="X" icon-size="sm" label="Effacer la recherche" class="absolute right-0 top-0" @click="clear" />
        </div>
        <div v-if="showHideToggle || $slots['extra-toggles']" class="flex shrink-0 flex-wrap items-center justify-end gap-2">
            <Button
                v-if="showHideToggle"
                size="sm"
                :variant="hideCompleted ? 'secondary' : 'ghost'"
                :aria-pressed="String(hideCompleted)"
                @click="$emit('update:hideCompleted', !hideCompleted)"
            >
                <Icon :icon="hideCompleted ? EyeOff : Eye" size="sm" />
                {{ hideLabel }}
            </Button>
            <slot name="extra-toggles"></slot>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, useId } from 'vue';
import { Eye, EyeOff, Search, X } from 'lucide-vue-next';
import Button from './ui/Button.vue';
import Icon from './ui/Icon.vue';
import IconButton from './ui/IconButton.vue';

const props = defineProps({
    search: { type: String, default: '' },
    hideCompleted: { type: Boolean, default: false },
    showHideToggle: { type: Boolean, default: true },
    placeholder: { type: String, default: 'Rechercher...' },
    hideLabel: { type: String, default: 'Masquer complétés' },
    debounceMs: { type: Number, default: 0 },
});

const emit = defineEmits(['update:search', 'update:hideCompleted', 'search-debounced']);

const inputId = useId();

let debounceTimer = null;

function onInput(event) {
    const value = event.target.value;
    emit('update:search', value);

    if (props.debounceMs > 0) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            emit('search-debounced', value);
        }, props.debounceMs);
    }
}

function clear() {
    emit('update:search', '');
    if (props.debounceMs > 0) {
        clearTimeout(debounceTimer);
        emit('search-debounced', '');
    }
}

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
});
</script>
