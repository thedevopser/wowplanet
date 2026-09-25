<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <div class="flex items-center gap-2">
                <span aria-hidden="true" class="size-2.5 rounded-full" :class="dotClass"></span>
                <span class="text-sm font-semibold text-default">{{ headline }}</span>
            </div>
            <span class="text-sm tabular-nums text-muted">écoulé {{ formatDuration(elapsedSeconds) }}</span>
            <span v-if="etaSeconds" class="text-sm tabular-nums text-muted">reste ~{{ formatDuration(etaSeconds) }}</span>
            <span class="text-sm tabular-nums text-muted">
                quota {{ formatCount(budget.used) }} / {{ formatCount(budget.ceiling) }}
            </span>
        </div>
        <p role="status" class="sr-only">{{ announcement }}</p>

        <ProgressBar :value="percent" aria-label="Avancement de l’import" />

        <p v-if="waiting" class="rounded-ui-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-default">
            <strong class="font-semibold text-warning">En attente :</strong> {{ waiting.message }}
        </p>

        <div v-if="steerable" class="flex flex-wrap items-center gap-3">
            <Button
                v-if="!isPaused"
                data-action="pause"
                size="sm"
                :disabled="steering !== null"
                @click="emit('pause')"
            >
                Mettre en pause
            </Button>
            <Button
                v-else
                data-action="resume"
                variant="primary"
                size="sm"
                :disabled="steering !== null"
                @click="emit('resume')"
            >
                Reprendre
            </Button>
            <Button
                data-action="cancel"
                variant="danger"
                size="sm"
                :disabled="steering !== null"
                @click="confirmingCancel = true"
            >
                Annuler l'import
            </Button>
            <span v-if="steering" class="text-sm text-muted">{{ steeringLabel }}, effective à la fin de la tranche en cours.</span>
        </div>

        <p v-if="isPaused" class="rounded-ui-md border border-info/40 bg-info/10 px-3 py-2 text-sm text-default">
            Import en pause depuis {{ formatDuration(interruptedFor ?? 0) }}.
            <span v-if="abandonedIn !== null">
                Sans reprise, il sera abandonné dans {{ formatDuration(abandonedIn) }} et le verrou relâché.
            </span>
        </p>

        <div v-if="confirmingCancel" class="space-y-3 rounded-ui-md border border-danger/40 bg-danger/10 p-4">
            <p class="text-sm text-default">
                L'entité en cours est abandonnée là où elle en est, sans suppression dans le catalogue.
                Les entités déjà importées conservent leur résultat, et le rapport dira que l'import a été interrompu.
            </p>
            <div class="flex flex-wrap gap-3">
                <Button data-action="confirm-cancel" variant="danger" @click="cancel">
                    Annuler l'import
                </Button>
                <Button data-action="keep-going" @click="confirmingCancel = false">
                    Le laisser continuer
                </Button>
            </div>
        </div>

        <ul class="space-y-1">
            <li
                v-for="step in steps"
                :key="step.stage"
                :data-status="step.status"
                class="flex flex-wrap items-baseline gap-x-2 text-sm"
            >
                <span class="w-32 shrink-0 font-medium" :class="stepColour(step.status)">{{ step.label }}</span>
                <span class="tabular-nums text-muted">{{ describe(step) }}</span>
            </li>
        </ul>

        <pre v-if="lines.length" class="max-h-72 overflow-y-auto whitespace-pre-wrap rounded-ui-md border border-default bg-background p-4 font-mono text-xs text-muted">{{ lines.join('\n') }}</pre>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Button from '../ui/Button.vue';
import ProgressBar from '../ui/ProgressBar.vue';
import { formatDuration } from '../../utils/formatDuration';

const props = defineProps({
    status: { type: String, required: true },
    stageLabel: { type: String, default: null },
    percent: { type: Number, default: 0 },
    elapsedSeconds: { type: Number, default: 0 },
    etaSeconds: { type: Number, default: null },
    budget: { type: Object, required: true },
    waiting: { type: Object, default: null },
    steps: { type: Array, default: () => [] },
    lines: { type: Array, default: () => [] },
    interruptedFor: { type: Number, default: null },
    abandonedIn: { type: Number, default: null },
    steering: { type: String, default: null },
});

const emit = defineEmits(['pause', 'resume', 'cancel']);

const confirmingCancel = ref(false);

const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled', 'not_found'];

const STEERING_LABELS = {
    pause: 'Pause demandée',
    resume: 'Reprise demandée',
    cancel: 'Annulation demandée',
};

const isPaused = computed(() => props.status === 'paused');
const steerable = computed(() => !TERMINAL_STATUSES.includes(props.status));
const steeringLabel = computed(() => STEERING_LABELS[props.steering] ?? '');

const cancel = () => {
    confirmingCancel.value = false;
    emit('cancel');
};

const SETTLED_HEADLINES = {
    completed: 'Import terminé',
    failed: 'Import terminé avec des échecs',
    cancelled: 'Import interrompu',
    paused: 'Import en pause',
    pending: 'Import en attente de démarrage',
};

const announcement = computed(() => SETTLED_HEADLINES[props.status] ?? (props.stageLabel ? `En cours — ${props.stageLabel}` : 'En cours'));

const headline = computed(() => (SETTLED_HEADLINES[props.status] || !props.stageLabel
    ? announcement.value
    : `${announcement.value} (${props.percent} %)`));

const dotClass = computed(() => {
    if (props.status === 'completed') return 'bg-success';
    if (props.status === 'failed') return 'bg-danger';
    if (props.status === 'cancelled') return 'border-2 border-strong';
    if (props.status === 'paused') return 'bg-info';

    return 'bg-warning motion-safe:animate-pulse';
});

function stepColour(status) {
    return {
        completed: 'text-success',
        failed: 'text-danger',
        running: 'text-info',
    }[status] ?? 'text-muted';
}

function describe(step) {
    if (step.status === 'pending') return 'à faire';
    if (step.status === 'skipped') return 'déjà à jour pour ce build';
    if (step.status === 'failed') return `échec : ${step.error ?? 'raison inconnue'}`;
    if (step.status === 'running') return `${step.percent} % · ${formatCount(step.api_calls)} appels`;

    return [
        `${formatCount(step.created)} ${plural(step.created, 'créée', 'créées')}`,
        `${formatCount(step.updated)} ${plural(step.updated, 'mise à jour', 'mises à jour')}`,
        `${formatCount(step.deleted)} ${plural(step.deleted, 'supprimée', 'supprimées')}`,
        `${formatCount(step.api_calls)} appels`,
        formatDuration(Math.round(step.duration_ms / 1000)),
    ].join(' · ');
}

function plural(value, singular, plural) {
    return value > 1 ? plural : singular;
}

function formatCount(value) {
    return Number(value).toLocaleString('fr-FR');
}
</script>
