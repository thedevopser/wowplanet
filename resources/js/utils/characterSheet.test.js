import { describe, it, expect } from 'vitest';
import { OVERVIEW, SECTIONS, UnknownSheetViewError, sheetUrl, viewOf } from './characterSheet';

describe('characterSheet', () => {
    it('lists the four sections of the sheet, the overview first', () => {
        expect(SECTIONS.map((section) => [section.value, section.label])).toEqual([
            [OVERVIEW, 'Aperçu'],
            ['progression', 'Progression'],
            ['endgame', 'Endgame'],
            ['collections', 'Collections'],
        ]);
    });

    it('mirrors the sub-tabs routed by the server', () => {
        const subs = Object.fromEntries(SECTIONS.map((section) => [section.value, section.subs.map((sub) => sub.value)]));

        expect(subs).toEqual({
            [OVERVIEW]: [],
            progression: ['quetes', 'hauts-faits', 'reputations', 'metiers'],
            endgame: ['mythique-plus', 'raids', 'pvp', 'equipement'],
            collections: ['montures', 'mascottes', 'decorations', 'garde-robe'],
        });
    });

    it('labels every sub-tab in full', () => {
        const labels = SECTIONS.flatMap((section) => section.subs.map((sub) => sub.label));

        expect(labels).toEqual(['Quêtes', 'Hauts-faits', 'Réputations', 'Métiers', 'Mythique+', 'Raids', 'PvP', 'Équipement et talents', 'Montures', 'Mascottes', 'Décorations', 'Garde-robe']);
    });

    describe('viewOf', () => {
        it('reads the overview from absent segments', () => {
            expect(viewOf(null, null)).toEqual({ section: OVERVIEW, sub: null });
        });

        it('opens the first sub-tab of a section given alone', () => {
            expect(viewOf('endgame', null)).toEqual({ section: 'endgame', sub: 'mythique-plus' });
        });

        it('keeps a known sub-tab', () => {
            expect(viewOf('collections', 'garde-robe')).toEqual({ section: 'collections', sub: 'garde-robe' });
        });

        it.each([['inventaire', null], ['progression', 'montures']])('refuses %s/%s', (section, sub) => {
            expect(() => viewOf(section, sub)).toThrow(UnknownSheetViewError);
        });
    });

    describe('sheetUrl', () => {
        it('points the overview to the base address', () => {
            expect(sheetUrl('hyjal', 'arthas', OVERVIEW, null)).toBe('/character/hyjal/arthas');
        });

        it('adds the section and sub-tab segments', () => {
            expect(sheetUrl('hyjal', 'arthas', 'collections', 'montures')).toBe('/character/hyjal/arthas/collections/montures');
        });

        it('encodes the realm and name', () => {
            expect(sheetUrl('conseil-des-ombres', 'élune', 'endgame', 'pvp')).toBe('/character/conseil-des-ombres/%C3%A9lune/endgame/pvp');
        });
    });
});
