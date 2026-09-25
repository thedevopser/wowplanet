import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RecordLine from './RecordLine.vue';

describe('RecordLine', () => {
    it('spells out wins, losses and the win rate', () => {
        const wrapper = mount(RecordLine, { props: { won: 15, lost: 12, winRate: 55.6 } });

        expect(wrapper.findAll('span').map((part) => part.text())).toEqual(['15 victoires', '12 défaites', '55.6 %']);
    });

    it('adds the games played when given', () => {
        expect(mount(RecordLine, { props: { played: 27, won: 15, lost: 12, winRate: 55.6 } }).text()).toContain('27 joués');
    });

    it('tells a positive record by its colour and its words', () => {
        const winning = mount(RecordLine, { props: { won: 15, lost: 12, winRate: 55.6 } });
        const losing = mount(RecordLine, { props: { won: 5, lost: 12, winRate: 29.4 } });

        expect(winning.find('[data-win-rate]').classes()).toContain('text-success');
        expect(losing.find('[data-win-rate]').classes()).toContain('text-muted');
    });
});
