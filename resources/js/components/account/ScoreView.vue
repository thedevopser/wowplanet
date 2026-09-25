<template>
    <div class="space-y-6">
        <Card v-if="status === 'computing'" data-computing class="mx-auto max-w-xl space-y-4 p-5 sm:p-6">
            <div role="status" aria-live="polite" class="space-y-1">
                <p class="text-lg font-semibold text-default">Analyse de {{ progress.current }}…</p>
                <p class="text-sm text-muted">
                    {{ progress.loaded }} / {{ progress.total }} personnages chargés<span v-if="progress.errors > 0" class="text-warning">
                        · {{ progress.errors }} erreur{{ progress.errors > 1 ? 's' : '' }}</span>
                </p>
            </div>
            <ProgressBar
                :value="processed"
                :max="Math.max(progress.total, 1)"
                :aria-label="`Personnages analysés : ${processed} sur ${progress.total}`"
            />
            <p class="text-xs text-subtle">Le résultat est gardé en cache pendant 24 heures.</p>
        </Card>

        <CardGridSkeleton v-else-if="status === 'loading'" label="Chargement de votre score" :count="3" />

        <ErrorState v-else-if="status === 'error'" title="Score indisponible" :message="errorMessage" @retry="startPolling" />

        <template v-else-if="status === 'ready' && score">
            <ScorePanel
                :score="score"
                title="Score du compte"
                :subtitle="panelSubtitle"
                :recommendations="recommendations"
                :share-data="shareData"
                share-variant="account"
            />

            <div class="flex justify-center">
                <Button variant="secondary" :disabled="refreshing" @click="refresh">
                    <Icon :icon="RotateCw" size="sm" :class="{ 'motion-safe:animate-spin': refreshing }" />
                    Recalculer
                </Button>
            </div>
        </template>

        <EmptyState
            v-else-if="status === 'ready'"
            :icon="Gauge"
            title="Aucun score à afficher"
            message="Le score du compte se calcule à partir de vos personnages, et aucun n’a été trouvé."
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { Gauge, RotateCw } from 'lucide-vue-next';
import { buildRecommendations } from '../../utils/scoreRecommendations';
import CardGridSkeleton from '../CardGridSkeleton.vue';
import ScorePanel from '../ScorePanel.vue';
import Button from '../ui/Button.vue';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import Icon from '../ui/Icon.vue';
import ProgressBar from '../ui/ProgressBar.vue';

const status = ref('loading');
const progress = ref({ loaded: 0, errors: 0, total: 0, current: '' });
const virtualProfile = ref(null);
const characterCount = ref(0);
const cachedAt = ref(null);
const errorMessage = ref('');
let pollTimer = null;

const score = computed(() => virtualProfile.value?.score || null);

const processed = computed(() => Math.min(progress.value.loaded + progress.value.errors, progress.value.total));
const refreshing = ref(false);

const rank = computed(() => score.value?.rank || 'Débutant');

const cachedAtFormatted = computed(() => {
    if (!cachedAt.value) return '';
    const d = new Date(cachedAt.value);
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
});

const panelSubtitle = computed(() => {
    const aggregated = `Agrégé sur ${characterCount.value} personnage${characterCount.value > 1 ? 's' : ''}`;

    return cachedAtFormatted.value ? `${aggregated} · mis à jour le ${cachedAtFormatted.value}` : aggregated;
});

const recommendations = computed(() => buildRecommendations(virtualProfile.value));

async function poll() {
    try {
        const resp = await axios.get('/api/account/score');
        const data = resp.data;

        if (data.status === 'computing') {
            status.value = 'computing';
            progress.value = data.progress;
            pollTimer = setTimeout(poll, 2500);
        } else if (data.status === 'ready') {
            stopPolling();
            if (data.data) {
                virtualProfile.value = data.data;
                characterCount.value = data.data.characterCount || 0;
                cachedAt.value = data.data.cachedAt || null;
                status.value = 'ready';
            } else {
                status.value = 'ready';
                virtualProfile.value = null;
            }
        }
    } catch (err) {
        stopPolling();
        if (err.response?.status === 401) {
            status.value = 'ready';
            virtualProfile.value = null;
        } else {
            status.value = 'error';
            errorMessage.value = err.response?.data?.message || 'Erreur lors du calcul du score.';
        }
    }
}

function startPolling() {
    status.value = 'loading';
    poll();
}

function stopPolling() {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

async function refresh() {
    refreshing.value = true;
    try {
        await axios.post('/api/account/score/refresh');
    } catch {
        // The next poll recomputes or reports the error itself.
    } finally {
        refreshing.value = false;
    }
    virtualProfile.value = null;
    startPolling();
}

const shareData = computed(() => {
    if (!score.value) return {};
    return {
        variant: 'account',
        characterCount: characterCount.value,
        globalScore: score.value.global,
        rank: rank.value,
        dimensions: score.value.dimensions,
    };
});

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});
</script>
