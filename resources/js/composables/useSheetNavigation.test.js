import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ router: { push: vi.fn() } }));

import { router } from '@inertiajs/vue3';
import { DIMENSION_VIEWS, useSheetNavigation } from './useSheetNavigation';

beforeEach(() => router.push.mockClear());

describe('useSheetNavigation', () => {
    it('builds the address of a view of the sheet', () => {
        const { urlOf } = useSheetNavigation(() => 'hyjal', () => 'arthas');

        expect(urlOf('collections', 'montures')).toBe('/character/hyjal/arthas/collections/montures');
        expect(urlOf('apercu', null)).toBe('/character/hyjal/arthas');
    });

    it('shows a view by rewriting the address and the history, without a request', () => {
        const { show } = useSheetNavigation(() => 'hyjal', () => 'arthas');

        show('endgame', 'raids');

        const visit = router.push.mock.calls[0][0];
        expect(visit.url).toBe('/character/hyjal/arthas/endgame/raids');
        expect(visit.preserveState).toBe(true);
        expect(visit.preserveScroll).toBe(true);
        expect(visit.props({ section: null, sub: null, other: 1 })).toEqual({ section: 'endgame', sub: 'raids', other: 1 });
    });

    it('clears the section props for the overview', () => {
        const { show } = useSheetNavigation(() => 'hyjal', () => 'arthas');

        show('apercu', null);

        expect(router.push.mock.calls[0][0].props({ section: 'endgame', sub: 'raids' })).toEqual({ section: null, sub: null });
    });

    it('maps every score dimension with a sub-tab to it', () => {
        expect(DIMENSION_VIEWS).toEqual({
            quests: { section: 'progression', sub: 'quetes' },
            achievements: { section: 'progression', sub: 'hauts-faits' },
            reputations: { section: 'progression', sub: 'reputations' },
            professions: { section: 'progression', sub: 'metiers' },
            raids: { section: 'endgame', sub: 'raids' },
            mounts: { section: 'collections', sub: 'montures' },
            pets: { section: 'collections', sub: 'mascottes' },
            decor: { section: 'collections', sub: 'decorations' },
            transmog: { section: 'collections', sub: 'garde-robe' },
        });
    });
});
