<template>
    <div>
        <label :for="inputId" class="mb-1 block text-sm font-medium text-default">{{ label }}</label>
        <ComboboxRoot
            v-model="chosen"
            v-model:open="open"
            ignore-filter
            :reset-search-term-on-blur="false"
            :reset-search-term-on-select="false"
            class="relative"
        >
            <ComboboxAnchor
                class="flex min-h-11 w-full items-center rounded-ui-md border border-strong bg-surface text-default
                    has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-accent"
            >
                <ComboboxInput
                    :id="inputId"
                    v-model="model"
                    :placeholder="placeholder"
                    class="min-w-0 flex-1 bg-transparent px-3 text-base text-default outline-none placeholder:text-subtle"
                />
                <ComboboxTrigger
                    :aria-label="`Afficher les valeurs de ${label}`"
                    class="inline-flex size-11 shrink-0 items-center justify-center text-subtle hover:text-default"
                >
                    <Icon :icon="ChevronsUpDown" size="sm" />
                </ComboboxTrigger>
            </ComboboxAnchor>
            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="4"
                    class="z-overlay max-h-72 w-(--reka-combobox-trigger-width) overflow-y-auto rounded-ui-md border border-default bg-surface p-1 text-default shadow-elevation-1
                        data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
                >
                    <ComboboxViewport>
                        <ComboboxItem
                            v-for="option in suggestions"
                            :key="option"
                            :value="option"
                            class="relative flex min-h-11 cursor-pointer items-center rounded-ui-sm py-2 pl-8 pr-3 text-sm outline-none
                                data-highlighted:bg-surface-raised data-[state=checked]:font-semibold"
                        >
                            <ComboboxItemIndicator class="absolute left-2 top-1/2 -translate-y-1/2 text-accent">
                                <Icon :icon="Check" size="sm" />
                            </ComboboxItemIndicator>
                            {{ option }}
                        </ComboboxItem>
                        <p v-if="!suggestions.length" data-combobox-new class="px-3 py-2 text-sm text-muted">
                            <template v-if="typed">Aucune valeur connue : « {{ typed }} » sera une nouvelle valeur.</template>
                            <template v-else>Aucune valeur connue.</template>
                        </p>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </div>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import Icon from './Icon.vue';

const props = defineProps({
    label: { type: String, required: true },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
});

// Free text: the value is whatever is typed, the options are only suggestions.
const model = defineModel({ type: String, default: '' });

const inputId = useId();
const open = ref(false);

const chosen = computed({
    get: () => (props.options.includes(model.value) ? model.value : undefined),
    set: (value) => {
        model.value = value ?? '';
    },
});

const typed = computed(() => model.value.trim());

// A value already chosen lists everything again, so that the list can be browsed to change it.
const suggestions = computed(() => {
    const needle = typed.value.toLocaleLowerCase('fr');
    if (!needle || props.options.includes(typed.value)) {
        return props.options;
    }

    return props.options.filter((option) => option.toLocaleLowerCase('fr').includes(needle));
});
</script>
