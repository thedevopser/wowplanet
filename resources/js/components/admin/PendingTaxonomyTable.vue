<template>
    <p v-if="entries.length === 0" class="text-sm text-muted">
        Rien à arbitrer ici : toutes les entrées de cette collection sont rangées.
    </p>

    <div v-else class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Entrées de collection à arbitrer">
        <table class="w-full text-sm">
            <caption class="sr-only">Entrées de collection à arbitrer</caption>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-muted border-b border-default">
                    <th class="py-2 pr-3 font-medium w-8">
                        <input
                            type="checkbox"
                            data-action="select-all"
                            :checked="allSelected"
                            :disabled="disabled"
                            aria-label="Sélectionner toutes les entrées affichées"
                            class="size-4 accent-accent disabled:opacity-50"
                            @change="toggleAll($event.target.checked)"
                        />
                    </th>
                    <th class="py-2 pr-3 font-medium text-right">ID</th>
                    <th class="py-2 pr-3 font-medium">Nom</th>
                    <th class="py-2 font-medium">Valeur d'attente</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in entries" :key="entry.id" class="border-b border-default last:border-0">
                    <td class="py-2.5 pr-3">
                        <input
                            type="checkbox"
                            data-action="select-entry"
                            :checked="selected.includes(entry.id)"
                            :disabled="disabled"
                            :aria-label="`Sélectionner ${entry.name}`"
                            class="size-4 accent-accent disabled:opacity-50"
                            @change="toggle(entry.id, $event.target.checked)"
                        />
                    </td>
                    <td class="py-2.5 pr-3 text-right font-mono text-xs tabular-nums text-muted">{{ entry.id }}</td>
                    <td class="py-2.5 pr-3 text-default">{{ entry.name }}</td>
                    <td data-role="pending-source" class="py-2.5 whitespace-nowrap">
                        <Badge v-if="entry.pending_source" tone="warning">{{ entry.pending_source }}</Badge>
                        <span v-else class="text-subtle">—</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Badge from '../ui/Badge.vue';

const props = defineProps({
    entries: { type: Array, required: true },
    selected: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:selected']);

const allSelected = computed(() => props.entries.length > 0
    && props.entries.every(entry => props.selected.includes(entry.id)));

function toggle(id, checked) {
    emit('update:selected', checked
        ? [...props.selected, id]
        : props.selected.filter(selected => selected !== id));
}

// La sélection ne porte que sur la page servie : cocher tout ne doit pas ramasser des
// entrées que l'écran n'a pas montrées.
function toggleAll(checked) {
    emit('update:selected', checked ? props.entries.map(entry => entry.id) : []);
}
</script>
