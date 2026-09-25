import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ProgressBar from './ProgressBar.vue';

const bar = (wrapper) => wrapper.find('[role="progressbar"]');

afterEach(() => {
    vi.restoreAllMocks();
});

describe('ProgressBar', () => {
    it('exposes its value to assistive technologies', () => {
        const progress = bar(mount(ProgressBar, { props: { value: 42, label: 'Quêtes' } }));

        expect(progress.attributes('aria-valuenow')).toBe('42');
        expect(progress.attributes('aria-valuemin')).toBe('0');
        expect(progress.attributes('aria-valuemax')).toBe('100');
    });

    it('is named by its visible label', () => {
        const wrapper = mount(ProgressBar, { props: { value: 42, label: 'Quêtes' } });
        const labelId = bar(wrapper).attributes('aria-labelledby');

        expect(wrapper.find(`#${labelId}`).text()).toBe('Quêtes');
    });

    it('can be named without a visible label', () => {
        const progress = bar(mount(ProgressBar, { props: { value: 42, ariaLabel: 'Avancement des quêtes' } }));

        expect(progress.attributes('aria-label')).toBe('Avancement des quêtes');
        expect(progress.attributes('aria-labelledby')).toBeUndefined();
    });

    it('warns when it has no name at all', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mount(ProgressBar, { props: { value: 42 } });

        expect(warn).toHaveBeenCalledWith(expect.stringContaining('[ProgressBar]'));
    });

    it('fills in proportion to its maximum', () => {
        const fill = mount(ProgressBar, { props: { value: 30, max: 120, label: 'Hauts-faits' } }).find('[data-progress-fill]');

        expect(fill.attributes('style')).toContain('width: 25%');
    });

    it('keeps the fill within the track when the value overshoots, and warns', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mount(ProgressBar, { props: { value: 150, label: 'Quêtes' } });

        expect(wrapper.find('[data-progress-fill]').attributes('style')).toContain('width: 100%');
        expect(bar(wrapper).attributes('aria-valuenow')).toBe('100');
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('[ProgressBar]'));
    });

    it('takes the accent colour by default and any colour when asked', () => {
        const byDefault = mount(ProgressBar, { props: { value: 10, label: 'Quêtes' } }).find('[data-progress-fill]');
        const coloured = mount(ProgressBar, { props: { value: 10, label: 'Quêtes', color: '#DDA73C' } }).find('[data-progress-fill]');

        expect(byDefault.attributes('style')).toContain('background-color: var(--color-accent)');
        expect(coloured.attributes('style')).toContain('background-color: #DDA73C');
    });

    it('never animates in a loop', () => {
        const html = mount(ProgressBar, { props: { value: 10, label: 'Quêtes' } }).html();

        expect(html).not.toMatch(/animate-/);
    });

    it('refuses a maximum that is not strictly positive', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mount(ProgressBar, { props: { value: 0, max: 0, label: 'Quêtes' } });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "max"');
        expect(wrapper.find('[data-progress-fill]').attributes('style')).toContain('width: 0%');
    });
});
