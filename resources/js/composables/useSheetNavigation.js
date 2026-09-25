import { router } from '@inertiajs/vue3';
import { OVERVIEW, sheetUrl } from '../utils/characterSheet';

// The sub-tab that details each dimension of the score.
export const DIMENSION_VIEWS = Object.freeze({
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

// Moving inside a sheet rewrites the address and the history without a request: the
// profile is already on the page, and the back button returns to the previous view.
export function useSheetNavigation(realm, name) {
    const urlOf = (section, sub) => sheetUrl(realm(), name(), section, sub);

    function show(section, sub) {
        router.push({
            url: urlOf(section, sub),
            props: (current) => ({ ...current, section: section === OVERVIEW ? null : section, sub }),
            preserveState: true,
            preserveScroll: true,
        });
    }

    return { urlOf, show };
}
