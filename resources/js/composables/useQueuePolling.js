import { onScopeDispose, ref, watch } from 'vue';
import axios from 'axios';

export const QUEUE_ENDPOINT = '/api/admin/health/queue';

/** Un job pris ou en attente se suit de près : il peut finir d'un instant à l'autre. */
export const BUSY_INTERVAL_MS = 5000;

/** File vide : un contrôle espacé suffit pour voir apparaître un job lancé après l'ouverture de l'onglet. */
export const IDLE_INTERVAL_MS = 30000;

const CLOCK_TICK_MS = 1000;

const hasJobs = queue => (queue.running?.length ?? 0) > 0 || (queue.waiting?.length ?? 0) > 0;

const currentSeconds = () => Math.floor(Date.now() / 1000);

/**
 * Suit la section Queue de la page Santé. Chaque mesure n'est programmée qu'après la
 * réponse de la précédente : FrankenPHP plante en dev sous requêtes concurrentes, et deux
 * mesures en vol ne diraient rien de plus qu'une.
 *
 * @param {() => object} source  la section rendue par la page, relue quand elle la recharge
 */
export function useQueuePolling(source) {
    const queue = ref(source());
    const interrupted = ref(false);
    const now = ref(currentSeconds());

    let pollTimer = null;
    let clockTimer = null;
    let inFlight = false;
    let disposed = false;

    const isHidden = () => document.visibilityState === 'hidden';

    const cancelPoll = () => {
        clearTimeout(pollTimer);
        pollTimer = null;
    };

    const schedule = () => {
        cancelPoll();

        if (disposed || isHidden()) {
            return;
        }

        pollTimer = setTimeout(measure, hasJobs(queue.value) ? BUSY_INTERVAL_MS : IDLE_INTERVAL_MS);
    };

    async function measure() {
        if (inFlight || disposed) {
            return;
        }

        cancelPoll();
        inFlight = true;

        try {
            const response = await axios.get(QUEUE_ENDPOINT);
            queue.value = response.data;
            interrupted.value = false;
        } catch {
            interrupted.value = true;
        } finally {
            inFlight = false;
        }

        schedule();
    }

    const startClock = () => {
        clearInterval(clockTimer);
        now.value = currentSeconds();
        clockTimer = setInterval(() => (now.value = currentSeconds()), CLOCK_TICK_MS);
    };

    const stopClock = () => {
        clearInterval(clockTimer);
        clockTimer = null;
    };

    const onVisibilityChange = () => {
        if (isHidden()) {
            cancelPoll();
            stopClock();

            return;
        }

        startClock();
        measure();
    };

    watch(source, section => {
        queue.value = section;

        if (!inFlight) {
            schedule();
        }
    });

    document.addEventListener('visibilitychange', onVisibilityChange);
    startClock();
    schedule();

    onScopeDispose(() => {
        disposed = true;
        cancelPoll();
        stopClock();
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return { queue, interrupted, now };
}
