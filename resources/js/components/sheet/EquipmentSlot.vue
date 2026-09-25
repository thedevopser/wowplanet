<template>
    <component
        :is="item ? 'a' : 'div'"
        :href="item ? `https://www.wowhead.com/fr/item=${item.item_id}` : undefined"
        :target="item ? '_blank' : undefined"
        :rel="item ? 'noopener' : undefined"
        class="flex min-h-11 items-center gap-3 rounded-ui-md border p-3"
        :class="item ? 'border-default bg-surface transition-colors duration-fast hover:bg-surface-raised' : 'border-dashed border-default'"
    >
        <span
            data-item-icon
            class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-ui-sm border-2 bg-surface-raised"
            :class="{ 'border-default': !color }"
            :style="color ? { borderColor: color.base } : undefined"
        >
            <img v-if="item?.icon_url" :src="item.icon_url" alt="" class="size-full object-cover" loading="lazy">
            <span v-else aria-hidden="true" class="text-xs font-semibold text-subtle">{{ label.charAt(0) }}</span>
        </span>
        <span class="min-w-0 flex-1">
            <span
                data-item-name
                class="block truncate text-sm font-semibold"
                :class="item ? 'text-default' : 'text-subtle'"
                :style="color ? { color: readable(color) } : undefined"
            >{{ item ? item.name : 'Vide' }}</span>
            <span class="block text-xs text-subtle">{{ label }}</span>
        </span>
        <span v-if="item" class="shrink-0 text-sm font-semibold tabular-nums text-muted">{{ item.item_level }}</span>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { qualityColor } from '../../utils/wowColors';
import { useWowColor } from '../../composables/useWowColor';

const props = defineProps({
    label: { type: String, required: true },
    item: { type: Object, default: null },
});

const { readable, safe } = useWowColor();

const color = computed(() => (props.item ? safe(qualityColor, props.item.quality) : null));
</script>
