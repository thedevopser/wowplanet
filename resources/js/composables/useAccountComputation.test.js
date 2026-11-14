import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { useAccountComputation } from './useAccountComputation';

vi.mock('axios');

const MESSAGES = { failed: 'Le calcul a échoué.', unavailable: 'Rien n’a pu être chargé.' };

let computation;

const Host = defineComponent({
    setup() {
        computation = useAccountComputation({ endpoint: '/api/account/week', refreshEndpoint: '/api/account/week/refresh', messages: MESSAGES });

        return () => h('div');
    },
});

const mountHost = async () => {
    const wrapper = mount(Host);
    await flushPromises();

    return wrapper;
};

describe('useAccountComputation', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('asks once it is mounted, loading until the answer', async () => {
        axios.get.mockReturnValue(new Promise(() => {}));

        await mountHost();

        expect(axios.get).toHaveBeenCalledWith('/api/account/week');
        expect(computation.status.value).toBe('loading');
    });

    it('keeps the answer once ready', async () => {
        axios.get.mockResolvedValueOnce({ data: { status: 'ready', data: { characters: [] } } });

        await mountHost();

        expect(computation.status.value).toBe('ready');
        expect(computation.data.value).toEqual({ characters: [] });
    });

    it('asks again every 2.5 s while the computation runs, keeping what it says of itself', async () => {
        axios.get
            .mockResolvedValueOnce({ data: { status: 'computing', step: 'characters', jobId: 'job-1' } })
            .mockResolvedValueOnce({ data: { status: 'ready', data: { characters: [] } } });

        await mountHost();

        expect(computation.status.value).toBe('computing');
        expect(computation.computing.value).toEqual({ status: 'computing', step: 'characters', jobId: 'job-1' });

        await vi.advanceTimersByTimeAsync(2499);
        expect(axios.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();

        expect(computation.status.value).toBe('ready');
    });

    it('waits ten seconds when the site is asked too often, without reporting an error', async () => {
        axios.get
            .mockRejectedValueOnce({ response: { status: 429 } })
            .mockResolvedValueOnce({ data: { status: 'ready', data: { characters: [] } } });

        await mountHost();
        expect(computation.status.value).toBe('loading');

        await vi.advanceTimersByTimeAsync(9999);
        expect(axios.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();

        expect(computation.status.value).toBe('ready');
    });

    it.each([
        [502, MESSAGES.failed],
        [500, MESSAGES.unavailable],
    ])('reports an answer %i with its message', async (status, message) => {
        axios.get.mockRejectedValueOnce({ response: { status } });

        await mountHost();

        expect(computation.status.value).toBe('error');
        expect(computation.errorMessage.value).toBe(message);
    });

    it('starts again on demand', async () => {
        axios.get.mockRejectedValueOnce({ response: { status: 500 } }).mockReturnValueOnce(new Promise(() => {}));

        await mountHost();
        computation.start();

        expect(computation.status.value).toBe('loading');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('drops the computation then asks again on refresh, even when the refresh fails', async () => {
        axios.get.mockResolvedValueOnce({ data: { status: 'ready', data: { characters: [] } } }).mockReturnValueOnce(new Promise(() => {}));
        axios.post.mockRejectedValueOnce({ response: { status: 500 } });

        await mountHost();
        const refreshing = computation.refresh();
        expect(computation.refreshing.value).toBe(true);
        await refreshing;

        expect(axios.post).toHaveBeenCalledWith('/api/account/week/refresh');
        expect(computation.refreshing.value).toBe(false);
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('stops asking once unmounted', async () => {
        axios.get.mockResolvedValueOnce({ data: { status: 'computing', jobId: 'job-1' } });

        const wrapper = await mountHost();
        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(5000);

        expect(axios.get).toHaveBeenCalledTimes(1);
    });
});
