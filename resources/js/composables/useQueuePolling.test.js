import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';
import axios from 'axios';
import { useQueuePolling, BUSY_INTERVAL_MS, IDLE_INTERVAL_MS, QUEUE_ENDPOINT } from './useQueuePolling';

vi.mock('axios');

const NOW = 1_700_000_000;

const job = (overrides = {}) => ({ label: 'Données des autres personnages', account: 'Thrall#1234', since: NOW - 72, ...overrides });

const section = (overrides = {}) => ({
    status: 'ok',
    issue: null,
    queue: 'imports',
    pending: 0,
    delayed: 0,
    reserved: 0,
    current: null,
    running: [],
    waiting: [],
    failed: [],
    ...overrides,
});

const setVisibility = state => {
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state });
    document.dispatchEvent(new Event('visibilitychange'));
};

let scope;

const follow = initial => {
    const source = ref(initial);
    scope = effectScope();
    const polling = scope.run(() => useQueuePolling(() => source.value));

    return { source, polling };
};

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    vi.setSystemTime(NOW * 1000);
    setVisibility('visible');
    axios.get = vi.fn().mockResolvedValue({ data: section() });
});

afterEach(() => {
    scope?.stop();
    vi.useRealTimers();
});

describe('useQueuePolling', () => {
    it('shows the section the page was rendered with before measuring anything', () => {
        const { polling } = follow(section({ running: [job()] }));

        expect(polling.queue.value.running).toEqual([job()]);
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('measures the queue every five seconds while a job is running', async () => {
        axios.get = vi.fn().mockResolvedValue({ data: section({ running: [job()] }) });
        follow(section({ running: [job()] }));

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS - 1);
        expect(axios.get).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(axios.get).toHaveBeenCalledWith(QUEUE_ENDPOINT);

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('keeps the fast pace while jobs are only waiting', async () => {
        follow(section({ waiting: [job()] }));

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);

        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('only checks every thirty seconds when the queue is idle', async () => {
        follow(section());

        await vi.advanceTimersByTimeAsync(IDLE_INTERVAL_MS - 1);
        expect(axios.get).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('picks up a job launched after the tab was opened, then speeds up', async () => {
        axios.get = vi.fn().mockResolvedValue({ data: section({ running: [job()] }) });
        const { polling } = follow(section());

        await vi.advanceTimersByTimeAsync(IDLE_INTERVAL_MS);
        expect(polling.queue.value.running).toEqual([job()]);

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('slows down again once the queue is empty', async () => {
        const { polling } = follow(section({ running: [job()] }));

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(polling.queue.value.running).toEqual([]);

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('never sends a measure while the previous one has not answered', async () => {
        let answer;
        axios.get = vi.fn().mockImplementation(() => new Promise(resolve => (answer = resolve)));
        follow(section({ running: [job()] }));

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS * 4);
        setVisibility('hidden');
        setVisibility('visible');
        expect(axios.get).toHaveBeenCalledTimes(1);

        answer({ data: section({ running: [job()] }) });
        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('stops measuring while the tab is hidden and measures at once when it comes back', async () => {
        follow(section({ running: [job()] }));

        setVisibility('hidden');
        await vi.advanceTimersByTimeAsync(IDLE_INTERVAL_MS * 2);
        expect(axios.get).not.toHaveBeenCalled();

        setVisibility('visible');
        await vi.advanceTimersByTimeAsync(0);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('stops measuring once the page is left', async () => {
        follow(section({ running: [job()] }));

        scope.stop();
        await vi.advanceTimersByTimeAsync(IDLE_INTERVAL_MS * 2);
        setVisibility('hidden');
        setVisibility('visible');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.get).not.toHaveBeenCalled();
    });

    it('keeps the last measure on a network failure, says so, and tries again at the next beat', async () => {
        axios.get = vi.fn().mockRejectedValueOnce(new Error('network')).mockResolvedValue({ data: section() });
        const { polling } = follow(section({ running: [job()] }));

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(polling.interrupted.value).toBe(true);
        expect(polling.queue.value.running).toEqual([job()]);

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(polling.interrupted.value).toBe(false);
        expect(polling.queue.value.running).toEqual([]);
    });

    it('advances the clock every second without asking the server', async () => {
        const { polling } = follow(section({ running: [job()] }));

        expect(polling.now.value).toBe(NOW);

        await vi.advanceTimersByTimeAsync(3000);

        expect(polling.now.value).toBe(NOW + 3);
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('adopts the section the page reloads with, and paces itself on it', async () => {
        const { source, polling } = follow(section());

        source.value = section({ waiting: [job()] });
        await nextTick();

        expect(polling.queue.value.waiting).toEqual([job()]);
        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('treats a section it could not measure as idle', async () => {
        follow(section({ status: 'unavailable', running: undefined, waiting: undefined }));

        await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);

        expect(axios.get).not.toHaveBeenCalled();
    });
});
