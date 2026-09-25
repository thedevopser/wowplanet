<template>
    <div class="space-y-4">
        <p v-if="files.length === 0" class="text-sm text-muted">
            Le magasin est vide : aucun fichier n'a encore été téléchargé.
        </p>

        <div v-else class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Fichiers du magasin de référence">
            <table class="w-full text-sm">
                <caption class="sr-only">Fichiers du magasin de référence</caption>
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-muted border-b border-default">
                        <th class="w-8 py-2 pr-3 font-medium"><span class="sr-only">Sélection</span></th>
                        <th class="py-2 pr-3 font-medium">Fichier</th>
                        <th class="py-2 pr-3 font-medium text-right">Taille</th>
                        <th class="py-2 pr-3 font-medium">Chargé le</th>
                        <th class="py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in files" :key="entry.filename" class="border-b border-default last:border-0 align-top">
                        <td class="py-2.5 pr-3">
                            <input
                                v-if="isSelectable(entry)"
                                type="checkbox"
                                data-action="select-file"
                                :checked="selected.includes(entry.filename)"
                                :disabled="disabled"
                                :aria-label="`Sélectionner ${entry.filename}`"
                                class="mt-1 size-4 accent-accent disabled:opacity-50"
                                @change="toggle(entry.filename, $event.target.checked)"
                            />
                        </td>
                        <td class="py-2.5 pr-3">
                            <span class="font-mono text-xs text-default break-all">{{ entry.filename }}</span>
                            <Badge :data-state="entry.state" :tone="BADGE_TONES[entry.state]" class="mt-1">{{ BADGES[entry.state] }}</Badge>
                            <span v-if="entry.source_table" class="ml-2 text-xs text-subtle">{{ entry.source_table }}</span>
                        </td>
                        <td class="py-2.5 pr-3 text-right font-mono tabular-nums text-default whitespace-nowrap">
                            {{ formatBytes(entry.bytes) }}
                        </td>
                        <td class="py-2.5 pr-3 text-muted whitespace-nowrap">
                            <template v-if="entry.loaded_at">
                                {{ formatDate(entry.loaded_at) }}
                                <span class="block font-mono text-xs text-subtle">{{ entry.build }}</span>
                            </template>
                            <span v-else class="text-subtle">—</span>
                        </td>
                        <td class="py-2.5 text-right">
                            <Button data-action="delete-file" variant="danger" size="sm" :disabled="disabled" @click="emit('delete', entry.filename)">
                                Supprimer
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="missing.length"
            data-alert="missing"
            class="p-4 rounded-ui-md border border-warning/40 bg-warning/10 space-y-1"
        >
            <p class="text-sm text-warning">
                {{ missing.length }} {{ missing.length > 1 ? 'chargements sont enregistrés' : 'chargement est enregistré' }}
                sans que le fichier soit encore sur le disque. Le catalogue est intact, mais ces chargements ne sont plus
                rouvrables.
            </p>
            <p v-for="entry in missing" :key="entry.filename" class="font-mono text-xs text-warning break-all">
                {{ entry.filename }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { formatBytes } from '../../utils/formatBytes';
import Badge from '../ui/Badge.vue';
import Button from '../ui/Button.vue';

const BADGES = {
    live: 'En service',
    obsolete: 'Obsolète',
    taxonomy: 'Taxonomie amont',
    orphan: 'Orphelin',
};

const BADGE_TONES = {
    live: 'success',
    obsolete: 'neutral',
    taxonomy: 'info',
    orphan: 'warning',
};

// Un fichier en service porte la preuve de ce qui est chargé, un instantané de taxonomie
// vient d'un tirage manuel : ni l'un ni l'autre ne s'attrape par une case, seulement par
// le bouton de suppression, un par un.
const SWEEPABLE = ['obsolete', 'orphan'];

const props = defineProps({
    files: { type: Array, required: true },
    missing: { type: Array, required: true },
    selected: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:selected', 'delete']);

function isSelectable(entry) {
    return SWEEPABLE.includes(entry.state);
}

function toggle(filename, checked) {
    emit('update:selected', checked
        ? [...props.selected, filename]
        : props.selected.filter(name => name !== filename));
}

function formatDate(timestamp) {
    return new Date(timestamp * 1000).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>
