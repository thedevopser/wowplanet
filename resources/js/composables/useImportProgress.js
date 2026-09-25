import { ref, computed } from 'vue';
import axios from 'axios';

/** Le suivi est un polling assumé : un seul administrateur, un objet observé qui dure des minutes. */
export const POLL_INTERVAL_MS = 1000;

/** Au-delà, les lignes les plus anciennes tombent : un import volumineux ne doit pas faire enfler l'onglet. */
export const MAX_LOG_LINES = 2000;

const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled', 'not_found'];

/** Un import en pause n'est pas terminé : le suivi continue de battre, c'est ce qui affiche le compte à rebours d'abandon et permet la reprise. */
const PAUSED_STATUS = 'paused';

/** L'ordre posé est tenu pour agi quand le suivi rapporte l'état qu'il visait. */
const SETTLED_BY = {
    pause: status => status === PAUSED_STATUS,
    resume: status => status !== PAUSED_STATUS,
    cancel: status => status === 'cancelled',
};

export function useImportProgress() {
    const jobId = ref(null);
    const status = ref('');
    const stage = ref(null);
    const stageLabel = ref(null);
    const percent = ref(0);
    const elapsedSeconds = ref(0);
    const etaSeconds = ref(null);
    const budget = ref({ used: 0, ceiling: 0 });
    const waiting = ref(null);
    const steps = ref([]);
    const lines = ref([]);
    const interruptedFor = ref(null);
    const abandonedIn = ref(null);
    const steering = ref(null);
    const error = ref('');

    let cursor = 0;
    let timer = null;

    const isRunning = computed(() => jobId.value !== null && !TERMINAL_STATUSES.includes(status.value));
    const isPaused = computed(() => status.value === PAUSED_STATUS);

    const stop = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    };

    const apply = data => {
        status.value = data.status;
        stage.value = data.stage ?? null;
        stageLabel.value = data.stage_label ?? null;
        percent.value = data.percent ?? 0;
        elapsedSeconds.value = data.elapsed_seconds ?? 0;
        etaSeconds.value = data.eta_seconds ?? null;
        budget.value = data.budget ?? { used: 0, ceiling: 0 };
        waiting.value = data.waiting ?? null;
        steps.value = data.steps ?? [];
        interruptedFor.value = data.interrupted_for ?? null;
        abandonedIn.value = data.abandoned_in ?? null;

        if (steering.value && SETTLED_BY[steering.value](status.value)) {
            steering.value = null;
        }

        const journal = data.log ?? { cursor, lines: [] };
        cursor = journal.cursor;
        lines.value = [...lines.value, ...journal.lines].slice(-MAX_LOG_LINES);
    };

    // Un échec de requête ne clôt pas le suivi : une coupure d'une seconde est
    // indiscernable d'un import qui continue, et c'est le serveur qui dit quand il finit.
    const poll = async () => {
        try {
            const response = await axios.get(`/api/admin/import/${jobId.value}?cursor=${cursor}`);
            apply(response.data);
        } catch {
            return;
        }

        if (!isRunning.value) {
            stop();
        }
    };

    const start = async id => {
        stop();
        jobId.value = id;
        cursor = 0;
        lines.value = [];
        status.value = '';

        await poll();

        if (isRunning.value) {
            timer = setInterval(poll, POLL_INTERVAL_MS);
        }
    };

    const attach = async () => {
        let running = null;

        try {
            const response = await axios.get('/api/admin/import/current');
            running = response.data.jobId;
        } catch {
            return;
        }

        if (running) {
            await start(running);
        }
    };

    // L'ordre n'interrompt rien par lui-même : il est posé côté serveur, et l'import s'y
    // range à sa frontière de tranche suivante. L'écran dit donc « demandé », pas « fait ».
    const steer = async order => {
        if (!jobId.value) {
            return;
        }

        error.value = '';
        steering.value = order;

        try {
            await axios.post(`/api/admin/import/${jobId.value}/${order}`);
        } catch (err) {
            steering.value = null;
            error.value = err.response?.data?.message || "Erreur lors du pilotage de l'import";

            return;
        }

        await poll();

        if (isRunning.value && timer === null) {
            timer = setInterval(poll, POLL_INTERVAL_MS);
        }
    };

    return {
        jobId,
        status,
        stage,
        stageLabel,
        percent,
        elapsedSeconds,
        etaSeconds,
        budget,
        waiting,
        steps,
        lines,
        interruptedFor,
        abandonedIn,
        steering,
        error,
        isRunning,
        isPaused,
        start,
        attach,
        stop,
        pause: () => steer('pause'),
        resume: () => steer('resume'),
        cancel: () => steer('cancel'),
    };
}
