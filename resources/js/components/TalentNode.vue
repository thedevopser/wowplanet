<template>
    <a
        v-if="activeEntry"
        :href="`https://www.wowhead.com/fr/spell=${activeEntry.spell_id}`"
        target="_blank"
        rel="noopener"
        :class="[
            'relative block overflow-hidden border-2 transition-opacity duration-fast',
            isSelected
                ? 'border-accent shadow-elevation-1'
                : 'border-strong opacity-40',
            node.type === 'choice' ? 'rounded-full' : 'rounded-md',
        ]"
        :style="{ width: size + 'px', height: size + 'px' }"
    >
        <img
            v-if="iconUrl && !iconErrored"
            :src="iconUrl"
            :alt="activeEntry.name"
            class="absolute inset-0 w-full h-full object-cover"
            loading="lazy"
            @error="iconErrored = true"
        />
        <div
            v-else
            class="absolute inset-0 bg-surface-raised"
        ></div>

        <!-- Rank badge -->
        <div
            v-if="node.max_rank > 1"
            :class="[
                'absolute -bottom-1 -right-1 z-10 rounded-ui-sm border bg-surface px-1 py-0.5 text-xs font-semibold leading-none tabular-nums',
                isSelected
                    ? 'border-accent text-accent'
                    : 'border-default text-subtle',
            ]"
        >{{ node.selected_rank }}/{{ node.max_rank }}</div>
    </a>
    <div
        v-else
        :class="[
            'border-2 border-strong bg-surface-raised opacity-40',
            node.type === 'choice' ? 'rounded-full' : 'rounded-md',
        ]"
        :style="{ width: size + 'px', height: size + 'px' }"
    ></div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    node: { type: Object, required: true },
    size: { type: Number, default: 56 },
});

const iconErrored = ref(false);

const isSelected = computed(() => props.node.selected_rank > 0);

const activeEntry = computed(() => {
    if (!props.node.entries || props.node.entries.length === 0) return null;

    if (props.node.type === 'choice') {
        const selected = props.node.entries.find(e => e.selected);
        return selected || props.node.entries[0];
    }

    return props.node.entries[0];
});

const iconUrl = computed(() => activeEntry.value?.icon_url || null);
</script>
