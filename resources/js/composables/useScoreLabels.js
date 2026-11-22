import { useTranslate } from './useTranslate.js';

// The server names the rank in French, which also keys its colour: only its label is translated.
const RANK_KEYS = Object.freeze({
    Légendaire: 'legendary',
    Épique: 'epic',
    Rare: 'rare',
    Commun: 'common',
    Débutant: 'beginner',
});

/**
 * The labels of the score in the language of the page: its rank and its dimensions.
 */
export function useScoreLabels() {
    const t = useTranslate();

    return {
        rankLabel: (rank) => (Object.hasOwn(RANK_KEYS, rank) ? t(`score.rank.${RANK_KEYS[rank]}`) : rank),
        dimensionLabel: (key) => t(`score.dimension.${key}`),
    };
}
