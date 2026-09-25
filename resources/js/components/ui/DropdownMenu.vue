<template>
    <DropdownMenuRoot>
        <DropdownMenuTrigger as-child>
            <slot name="trigger" />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                :aria-label="label"
                align="end"
                :side-offset="4"
                class="z-overlay min-w-48 rounded-ui-md border border-default bg-surface p-1 text-default shadow-elevation-1
                    data-[state=open]:animate-fade-in data-[state=closed]:animate-fade-out"
            >
                <template v-if="choices">
                    <DropdownMenuLabel :id="choicesLabelId" class="px-3 pb-1 pt-2 text-xs font-medium text-subtle">{{ choices.label }}</DropdownMenuLabel>
                    <DropdownMenuRadioGroup v-model="choice" :aria-labelledby="choicesLabelId">
                        <DropdownMenuRadioItem
                            v-for="option in choices.options"
                            :key="option.value"
                            :value="option.value"
                            :class="[ITEM_CLASSES, 'text-default']"
                        >
                            <span class="inline-flex size-4 items-center justify-center">
                                <DropdownMenuItemIndicator>
                                    <Icon :icon="Check" size="sm" />
                                </DropdownMenuItemIndicator>
                            </span>
                            {{ option.label }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </template>
                <template v-for="group in groups" :key="group.name">
                    <DropdownMenuSeparator v-if="group.separated || choices" class="my-1 border-t border-default" />
                    <DropdownMenuItem
                        v-for="item in group.items"
                        :key="item.key"
                        :as-child="Boolean(item.href)"
                        :class="[ITEM_CLASSES, item.tone === DANGER ? 'text-danger' : 'text-default']"
                        @select="emit('select', item.key)"
                    >
                        <a v-if="item.href && item.external" :href="item.href" target="_blank" rel="noopener noreferrer">
                            <Icon v-if="item.icon" :icon="item.icon" size="sm" />
                            {{ item.label }}
                        </a>
                        <Link v-else-if="item.href" :href="item.href">
                            <Icon v-if="item.icon" :icon="item.icon" size="sm" />
                            {{ item.label }}
                        </Link>
                        <template v-else>
                            <Icon v-if="item.icon" :icon="item.icon" size="sm" />
                            {{ item.label }}
                        </template>
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>

<script>
const DANGER = 'danger';
const TONES = ['default', DANGER];

const isValidItem = (item) => typeof item?.key === 'string'
    && typeof item.label === 'string'
    && (item.tone === undefined || TONES.includes(item.tone));

const isValidChoices = (choices) => choices === null || (typeof choices.label === 'string'
    && Array.isArray(choices.options)
    && choices.options.every((option) => typeof option?.value === 'string' && typeof option.label === 'string'));
</script>

<script setup>
import { computed, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuItemIndicator,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import Icon from './Icon.vue';

const ITEM_CLASSES = 'flex h-11 w-full cursor-pointer items-center gap-2 rounded-ui-sm px-3 text-sm outline-none '
    + 'data-highlighted:bg-surface-raised';

const props = defineProps({
    items: { type: Array, required: true, validator: (items) => items.every(isValidItem) },
    label: { type: String, required: true },
    choices: { type: Object, default: null, validator: isValidChoices },
});

const emit = defineEmits(['select']);

const choice = defineModel('choice', { type: String, default: '' });
const choicesLabelId = useId();

// Destructive actions always come last, set apart by a separator.
const groups = computed(() => {
    const regular = props.items.filter((item) => item.tone !== DANGER);
    const destructive = props.items.filter((item) => item.tone === DANGER);

    return [
        { name: 'regular', separated: false, items: regular },
        { name: 'destructive', separated: regular.length > 0, items: destructive },
    ].filter((group) => group.items.length > 0);
});
</script>
