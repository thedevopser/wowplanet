import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import axios from 'axios';
import { useImportProgress, POLL_INTERVAL_MS, MAX_LOG_LINES } from './useImportProgress';

vi.mock('axios');

const progress = (overrides = {}) => ({
    data: {
        status: 'running',
        stage: 'quests',
        stage_label: 'Quêtes',
        percent: 25,
        elapsed_seconds: 12,
        eta_seconds: 90,
        budget: { used: 1200, ceiling: 30000 },
        waiting: null,
        steps: [],
        log: { cursor: 0, lines: [] },
        ...overrides,
    },
});

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    axios.get = vi.fn().mockResolvedValue(progress());
});

afterEach(() => vi.useRealTimers());

describe('useImportProgress', () => {
    it('follows nothing until it is asked to', () => {
        const tracking = useImportProgress();

        expect(tracking.jobId.value).toBeNull();
        expect(tracking.isRunning.value).toBe(false);
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('reads the progress as soon as it starts following a job', async () => {
        const tracking = useImportProgress();

        await tracking.start('job-1');

        expect(axios.get).toHaveBeenCalledWith('/api/admin/import/job-1?cursor=0');
        expect(tracking.stageLabel.value).toBe('Quêtes');
        expect(tracking.percent.value).toBe(25);
        expect(tracking.budget.value.used).toBe(1200);
    });

    it('polls again every second while the import runs', async () => {
        const tracking = useImportProgress();
        await tracking.start('job-1');

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 3);

        expect(axios.get).toHaveBeenCalledTimes(4);

        tracking.stop();
    });

    it('presents the cursor it was handed back, so a poll only carries what is new', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce(progress({ log: { cursor: 2, lines: ['une', 'deux'] } }))
            .mockResolvedValue(progress({ log: { cursor: 3, lines: ['trois'] } }));

        const tracking = useImportProgress();
        await tracking.start('job-1');
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);

        expect(axios.get).toHaveBeenLastCalledWith('/api/admin/import/job-1?cursor=2');
        expect(tracking.lines.value).toEqual(['une', 'deux', 'trois']);

        tracking.stop();
    });

    it('stops polling once the import is over, and keeps its report on screen', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce(progress({ log: { cursor: 1, lines: ['une'] } }))
            .mockResolvedValue(progress({ status: 'completed', log: { cursor: 2, lines: ['Import terminé.'] } }));

        const tracking = useImportProgress();
        await tracking.start('job-1');
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);

        expect(tracking.isRunning.value).toBe(false);
        expect(tracking.lines.value).toEqual(['une', 'Import terminé.']);

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 5);

        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('stops polling on a failed import', async () => {
        axios.get = vi.fn().mockResolvedValue(progress({ status: 'failed' }));

        const tracking = useImportProgress();
        await tracking.start('job-1');

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 3);

        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(tracking.isRunning.value).toBe(false);
    });

    it('stops polling on an import that is nowhere to be found', async () => {
        axios.get = vi.fn().mockResolvedValue(progress({ status: 'not_found' }));

        const tracking = useImportProgress();
        await tracking.start('job-1');

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 3);

        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('keeps following after a poll fails, since a blip is not the end of an import', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce(progress())
            .mockRejectedValueOnce(new Error('boom'))
            .mockResolvedValue(progress());

        const tracking = useImportProgress();
        await tracking.start('job-1');
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 2);

        expect(tracking.isRunning.value).toBe(true);
        expect(axios.get).toHaveBeenCalledTimes(3);

        tracking.stop();
    });

    it('says why the import waits', async () => {
        axios.get = vi.fn().mockResolvedValue(progress({
            waiting: { reason: 'hourly_budget', seconds: 240, message: 'plafond horaire atteint, reprise dans 240 s' },
        }));

        const tracking = useImportProgress();
        await tracking.start('job-1');

        expect(tracking.waiting.value.message).toBe('plafond horaire atteint, reprise dans 240 s');

        tracking.stop();
    });

    it('bounds the journal it keeps in memory', async () => {
        const flood = Array.from({ length: MAX_LOG_LINES + 50 }, (_, i) => `ligne ${i}`);
        axios.get = vi.fn().mockResolvedValue(progress({ log: { cursor: flood.length, lines: flood } }));

        const tracking = useImportProgress();
        await tracking.start('job-1');

        expect(tracking.lines.value).toHaveLength(MAX_LOG_LINES);
        expect(tracking.lines.value.at(-1)).toBe(`ligne ${flood.length - 1}`);

        tracking.stop();
    });

    it('picks up an import that was already running when the panel opened', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: 'job-7' } })
            .mockResolvedValue(progress());

        const tracking = useImportProgress();
        await tracking.attach();

        expect(axios.get).toHaveBeenNthCalledWith(1, '/api/admin/import/current');
        expect(tracking.jobId.value).toBe('job-7');
        expect(tracking.stageLabel.value).toBe('Quêtes');

        tracking.stop();
    });

    it('stays idle when no import is running', async () => {
        axios.get = vi.fn().mockResolvedValue({ data: { jobId: null } });

        const tracking = useImportProgress();
        await tracking.attach();

        expect(tracking.jobId.value).toBeNull();
        expect(tracking.isRunning.value).toBe(false);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('stays idle when the panel cannot even ask what is running', async () => {
        axios.get = vi.fn().mockRejectedValue(new Error('boom'));

        const tracking = useImportProgress();
        await tracking.attach();

        expect(tracking.jobId.value).toBeNull();
        expect(tracking.isRunning.value).toBe(false);
    });

    it('drops the journal of the previous import when another one starts', async () => {
        axios.get = vi.fn().mockResolvedValue(progress({ log: { cursor: 1, lines: ['une'] } }));

        const tracking = useImportProgress();
        await tracking.start('job-1');
        await tracking.start('job-2');

        expect(tracking.lines.value).toEqual(['une']);
        expect(axios.get).toHaveBeenLastCalledWith('/api/admin/import/job-2?cursor=0');

        tracking.stop();
    });

    it('stops polling when told to', async () => {
        const tracking = useImportProgress();
        await tracking.start('job-1');

        tracking.stop();
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 5);

        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('keeps following a paused import, which is not over', async () => {
        axios.get = vi.fn().mockResolvedValue(progress({ status: 'paused', interrupted_for: 120, abandoned_in: 3480 }));
        const tracking = useImportProgress();

        await tracking.start('job-1');

        expect(tracking.isRunning.value).toBe(true);
        expect(tracking.isPaused.value).toBe(true);
        expect(tracking.interruptedFor.value).toBe(120);
        expect(tracking.abandonedIn.value).toBe(3480);

        tracking.stop();
    });

    it('stops following an import once it has been cancelled', async () => {
        axios.get = vi.fn().mockResolvedValue(progress({ status: 'cancelled' }));
        const tracking = useImportProgress();

        await tracking.start('job-1');
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 3);

        expect(tracking.isRunning.value).toBe(false);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('asks the server to pause, resume or cancel the import it follows', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        const tracking = useImportProgress();
        await tracking.start('job-1');

        await tracking.pause();
        await tracking.resume();
        await tracking.cancel();

        expect(axios.post).toHaveBeenNthCalledWith(1, '/api/admin/import/job-1/pause');
        expect(axios.post).toHaveBeenNthCalledWith(2, '/api/admin/import/job-1/resume');
        expect(axios.post).toHaveBeenNthCalledWith(3, '/api/admin/import/job-1/cancel');

        tracking.stop();
    });

    it('says an order has been sent until the import acts on it', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        const tracking = useImportProgress();
        await tracking.start('job-1');

        await tracking.pause();

        expect(tracking.steering.value).toBe('pause');

        axios.get = vi.fn().mockResolvedValue(progress({ status: 'paused' }));
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);

        expect(tracking.steering.value).toBeNull();

        tracking.stop();
    });

    it('reports an order the server refused, without pretending it went through', async () => {
        axios.post = vi.fn().mockRejectedValue({ response: { data: { message: "Cet import n'est plus celui qui tourne." } } });
        const tracking = useImportProgress();
        await tracking.start('job-1');

        await tracking.cancel();

        expect(tracking.steering.value).toBeNull();
        expect(tracking.error.value).toBe("Cet import n'est plus celui qui tourne.");

        tracking.stop();
    });

    it('steers nothing when it follows nothing', async () => {
        axios.post = vi.fn();
        const tracking = useImportProgress();

        await tracking.pause();

        expect(axios.post).not.toHaveBeenCalled();
    });
});
