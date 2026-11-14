import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const POLL_INTERVAL = 2500;
const THROTTLED_RETRY_DELAY = 10000;
const FAILED_COMPUTATION = 502;
const TOO_MANY_REQUESTS = 429;

// A view of the account whose data the server computes in the background: it asks until the
// answer is ready, and keeps what the server says of the computation in between.
export function useAccountComputation({ endpoint, refreshEndpoint, messages }) {
    const status = ref('loading');
    const computing = ref(null);
    const data = ref(null);
    const errorMessage = ref('');
    const refreshing = ref(false);
    let pollTimer = null;

    function stop() {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    function show(answer) {
        if (answer.status === 'computing') {
            status.value = 'computing';
            computing.value = answer;
            pollTimer = setTimeout(poll, POLL_INTERVAL);

            return;
        }

        data.value = answer.data;
        status.value = 'ready';
    }

    function fail(error) {
        status.value = 'error';
        errorMessage.value = error.response?.status === FAILED_COMPUTATION ? messages.failed : messages.unavailable;
    }

    async function poll() {
        try {
            const response = await axios.get(endpoint);
            show(response.data);
        } catch (error) {
            stop();
            if (error.response?.status === TOO_MANY_REQUESTS) {
                // The session limiter counts every call of the site: wait for it rather than give up.
                pollTimer = setTimeout(poll, THROTTLED_RETRY_DELAY);

                return;
            }
            fail(error);
        }
    }

    function start() {
        stop();
        status.value = 'loading';
        poll();
    }

    async function refresh() {
        refreshing.value = true;
        try {
            await axios.post(refreshEndpoint);
        } catch {
            // The next request recomputes or reports the error itself.
        } finally {
            refreshing.value = false;
        }
        start();
    }

    onMounted(start);
    onUnmounted(stop);

    return { status, computing, data, errorMessage, refreshing, start, refresh };
}
