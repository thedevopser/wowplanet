<template>
    <Card
        as="section"
        data-role="build-status"
        :data-state="state"
        :aria-labelledby="headingId"
        class="border-l-4 p-5 sm:p-6"
        :class="RULES[state]"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 :id="headingId" class="mb-1 font-display text-2xl font-semibold text-default">Build servi et état des imports</h2>
                <p class="text-sm" :class="HEADLINES[state].class">{{ HEADLINES[state].text }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button data-action="check" size="sm" :disabled="disabled || checking" @click="emit('check')">
                    {{ checking ? 'Vérification…' : 'Revérifier' }}
                </Button>
                <Button
                    v-if="status.behind.length"
                    data-action="update-all"
                    variant="primary"
                    size="sm"
                    :disabled="disabled"
                    @click="emit('update', status.behind)"
                >
                    Mettre à jour {{ status.behind.length }} entité{{ status.behind.length > 1 ? 's' : '' }}
                </Button>
            </div>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <div
                v-for="(upstream, source) in status.upstreams"
                :key="source"
                data-role="upstream"
                :data-source="source"
                class="rounded-ui-md bg-surface-raised px-4 py-3"
            >
                <span class="text-xs uppercase tracking-wide text-muted">{{ upstream.label }}</span>
                <span class="block font-mono text-sm" :class="upstream.reachable ? 'text-default' : 'text-muted'">
                    {{ upstream.build ?? 'Build jamais lu' }}
                </span>
                <!-- La date de lecture est affichée même quand tout va bien : sans elle,
                     une revérification qui ne change aucun build ne se verrait pas, et
                     rien ne dirait que le clic a été suivi d'effet. -->
                <span v-if="upstream.reachable && upstream.checked_at" class="mt-1 block text-xs text-muted">
                    Lu le {{ formatDateTime(upstream.checked_at) }}
                </span>
                <span
                    v-if="! upstream.reachable"
                    data-alert="unreachable"
                    class="mt-1 block text-xs text-warning"
                >
                    Vérification non aboutie{{ upstream.checked_at ? `, valeur lue le ${formatDateTime(upstream.checked_at)}` : ', cet amont n\'a jamais répondu' }}
                </span>
            </div>
        </div>

        <BuildStatusEntryList
            class="mt-5"
            :entries="status.entries"
            :disabled="disabled"
            @update="stage => emit('update', stage)"
        />
    </Card>
</template>

<script setup>
import { computed, useId } from 'vue';
import Button from '../ui/Button.vue';
import Card from '../ui/Card.vue';
import BuildStatusEntryList from './BuildStatusEntryList.vue';

const props = defineProps({
    status: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    checking: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'check']);

const headingId = useId();

const RULES = {
    'up-to-date': 'border-l-success',
    behind: 'border-l-danger',
    inconclusive: 'border-l-warning',
};

const HEADLINES = {
    'up-to-date': { text: 'Tout est à jour : aucune entité ne traîne derrière le build servi.', class: 'text-muted' },
    behind: { text: 'Un patch est passé : les entités ci-dessous sont restées sur un build antérieur.', class: 'text-danger' },
    inconclusive: { text: 'La vérification n\'a pas abouti : l\'état ci-dessous est ce qu\'on savait la dernière fois.', class: 'text-warning' },
};

// Une vérification qui n'a pas abouti prime sur tout le reste : c'est la seule façon de
// ne jamais annoncer que tout va bien sur la foi d'un appel raté.
const state = computed(() => {
    if (! props.status.is_conclusive) return 'inconclusive';

    return props.status.is_up_to_date ? 'up-to-date' : 'behind';
});

function formatDateTime(iso) {
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}
</script>
