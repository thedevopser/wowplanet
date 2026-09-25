<template>
    <div class="relative overflow-x-auto rounded-ui-md border border-default bg-surface" tabindex="0" role="region" :aria-label="caption">
        <table
            :aria-busy="String(busy)"
            class="w-full text-sm transition-opacity duration-fast"
            :class="{ 'opacity-60': busy }"
        >
            <caption class="sr-only">{{ caption }}</caption>
            <thead class="border-b border-default bg-surface-raised">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-3 py-2.5 text-xs font-semibold text-muted"
                        :class="cellClasses(column)"
                    >
                        <span v-if="column.visuallyHidden" class="sr-only">{{ column.label }}</span>
                        <template v-else>{{ column.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in rows"
                    :key="keyOf(row, index)"
                    class="border-b border-default last:border-b-0 hover:bg-surface-raised"
                    v-bind="rowAttrs(row)"
                >
                    <td v-for="column in columns" :key="column.key" class="px-3 py-2" :class="cellClasses(column)">
                        <slot :name="`cell-${column.key}`" :row="row">{{ column.visuallyHidden ? '' : row[column.key] }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
const HIDDEN_BELOW = Object.freeze({ sm: 'hidden sm:table-cell', md: 'hidden md:table-cell' });

const props = defineProps({
    caption: { type: String, required: true },
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    rowKey: { type: String, default: 'id' },
    rowAttrs: { type: Function, default: () => ({}) },
    busy: { type: Boolean, default: false },
});

const keyOf = (row, index) => row[props.rowKey] ?? index;

const cellClasses = (column) => [
    column.align === 'end' ? 'text-right' : 'text-left',
    HIDDEN_BELOW[column.hideBelow] ?? '',
    column.class ?? '',
];
</script>
