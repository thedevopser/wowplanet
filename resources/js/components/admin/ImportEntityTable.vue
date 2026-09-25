<template>
    <div class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Entités importables">
        <table class="w-full text-sm">
            <caption class="sr-only">Entités importables</caption>
            <thead>
                <tr class="border-b border-default text-left text-xs uppercase tracking-wide text-muted">
                    <th class="py-2 pr-3 font-medium"><span class="sr-only">Sélection</span></th>
                    <th class="py-2 pr-3 font-medium">Entité</th>
                    <th class="py-2 pr-3 text-right font-medium">Lignes en base</th>
                    <th class="py-2 pr-3 font-medium">Dernier import</th>
                    <th class="py-2 text-right font-medium">Coût forcé</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entity in entities" :key="entity.stage" class="border-b border-default last:border-0">
                    <td class="py-2.5 pr-3">
                        <input
                            type="checkbox"
                            :value="entity.stage"
                            :checked="selection.includes(entity.stage)"
                            :disabled="disabled"
                            :aria-label="`Sélectionner ${entity.label}`"
                            class="size-4 accent-accent disabled:opacity-50"
                            @change="toggle(entity.stage, $event.target.checked)"
                        >
                    </td>
                    <td class="whitespace-nowrap py-2.5 pr-3 font-medium text-default">{{ entity.label }}</td>
                    <td class="py-2.5 pr-3 text-right font-mono tabular-nums text-default">{{ formatCount(entity.rows) }}</td>
                    <td class="py-2.5 pr-3 text-muted">
                        <template v-if="entity.imported_at">
                            {{ formatDate(entity.imported_at) }}
                            <span class="ml-1 font-mono text-xs text-subtle">{{ entity.build }}</span>
                        </template>
                        <span v-else class="text-warning">Jamais importée</span>
                    </td>
                    <td class="whitespace-nowrap py-2.5 text-right font-mono text-xs tabular-nums text-muted">~{{ formatCount(entity.estimated_api_calls) }} appels</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
const props = defineProps({
    entities: { type: Array, required: true },
    selection: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:selection']);

function toggle(stage, checked) {
    const next = checked
        ? [...props.selection, stage]
        : props.selection.filter(selected => selected !== stage);

    emit('update:selection', next);
}

function formatCount(value) {
    return Number(value).toLocaleString('fr-FR');
}

function formatDate(iso) {
    return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}
</script>
