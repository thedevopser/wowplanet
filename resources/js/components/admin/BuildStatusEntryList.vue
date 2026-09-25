<template>
    <div class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Build importé et build servi par entité">
        <table class="w-full text-sm">
            <caption class="sr-only">Build importé et build servi par entité</caption>
            <thead>
                <tr class="border-b border-default text-left text-xs uppercase tracking-wide text-muted">
                    <th class="py-2 pr-3 font-medium">Entité</th>
                    <th class="py-2 pr-3 font-medium">Build importé</th>
                    <th class="py-2 pr-3 font-medium">Build servi</th>
                    <th class="py-2 pr-3 font-medium">Dernier import</th>
                    <th class="py-2 font-medium"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="entry in entries"
                    :key="entry.stage"
                    :data-stage="entry.stage"
                    class="border-b border-default align-top last:border-0"
                >
                    <td class="whitespace-nowrap py-2.5 pr-3">
                        <span class="font-medium text-default">{{ entry.label }}</span>
                        <span class="block text-xs text-subtle">{{ UPSTREAM_LABELS[entry.upstream] }}</span>
                        <Badge
                            v-if="ALERTS[entry.state]"
                            :data-alert="entry.state"
                            :tone="ALERTS[entry.state].tone"
                            class="mt-1"
                        >{{ ALERTS[entry.state].label }}</Badge>
                        <span v-if="entry.note" class="mt-1 block text-xs text-muted">{{ entry.note }}</span>
                        <Link
                            v-if="entry.stage === 'reference'"
                            href="/admin/reference"
                            class="inline-flex min-h-11 items-center text-xs text-accent hover:underline"
                        >
                            Détail table par table
                        </Link>
                    </td>
                    <td class="py-2.5 pr-3 font-mono text-xs" :class="entry.state === 'stale' ? 'text-info' : 'text-muted'">
                        {{ entry.build ?? '—' }}
                    </td>
                    <td class="py-2.5 pr-3 font-mono text-xs text-muted">
                        {{ entry.upstream_build ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap py-2.5 pr-3 text-muted">
                        {{ entry.imported_at ? formatDate(entry.imported_at) : '—' }}
                    </td>
                    <td class="py-2.5 text-right">
                        <Button
                            data-action="update-stage"
                            size="sm"
                            :disabled="disabled || ! isUpdatable(entry)"
                            :aria-label="`Mettre à jour ${entry.label}`"
                            @click="emit('update', entry.stage)"
                        >
                            Mettre à jour
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import Badge from '../ui/Badge.vue';
import Button from '../ui/Button.vue';

defineProps({
    entries: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update']);

const UPSTREAM_LABELS = {
    blizzard: 'API Blizzard',
    wago: 'wago.tools',
};

const ALERTS = {
    stale: { label: 'Build antérieur', tone: 'info' },
    never: { label: 'Jamais importée', tone: 'warning' },
    unknown: { label: 'Non comparable', tone: 'neutral' },
};

// Une entité qu'on n'a pas pu situer n'est pas relançable : le bouton partirait sur une
// supposition, et c'est exactement ce qu'un appel raté ne permet pas.
function isUpdatable(entry) {
    return entry.state === 'stale' || entry.state === 'never';
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>
