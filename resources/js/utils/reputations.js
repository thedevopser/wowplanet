// Reputation standings as the sheet shows them: account-wide factions take the best
// standing of the account, character-bound ones point to a better character.

const EXALTED = '7';
const RENOWN = 'renown';
const LAST_CLASSIC_TIER = 7;
const LEVEL_NAME = /^(Niveau|Level) \d+$/;

const frenchRenown = (level) => `Renom ${level}`;

const renownName = (best, renownLabel) => (best.renown_level > 0 ? renownLabel(best.renown_level) : best.standing_name);

function withBest(faction, best, renownLabel) {
    return {
        ...faction,
        tier: best.tier,
        raw: best.raw,
        renown_level: best.renown_level,
        standing_name: renownName(best, renownLabel),
        completed: best.completed || faction.completed,
        started: true,
    };
}

export function effectiveStanding(faction, best, renownLabel = frenchRenown) {
    if (!faction.account_wide || !best) {
        return faction;
    }
    if (faction.started === false) {
        return withBest(faction, best, renownLabel);
    }

    const ownIsBetter = (faction.renown_level > 0 || best.renown_level > 0)
        ? faction.renown_level >= best.renown_level
        : faction.raw >= best.raw;

    return ownIsBetter ? faction : withBest(faction, best, renownLabel);
}

export function betterElsewhere(faction, best, characterName) {
    if (faction.account_wide || !best || best.character_name === characterName || best.raw <= (faction.raw || 0)) {
        return null;
    }

    return best;
}

export function sortFactions(factions, effectiveOf) {
    return factions.toSorted((a, b) => {
        const first = effectiveOf(a);
        const second = effectiveOf(b);

        if (first.started !== second.started) {
            return first.started ? -1 : 1;
        }
        if (first.started && second.started && first.completed !== second.completed) {
            return first.completed ? 1 : -1;
        }

        return a.name.localeCompare(b.name, 'fr');
    });
}

// Whatever its scale, a finished standing shines like exaltation. Renowns, companion
// levels and friendships beyond the classic tiers take the renown colour while they go on.
export function standingKey(standing) {
    if (standing.started === false) {
        return null;
    }
    if (standing.completed) {
        return EXALTED;
    }
    if (standing.renown_level > 0 || standing.tier > LAST_CLASSIC_TIER) {
        return RENOWN;
    }

    return String(standing.tier);
}

// Blizzard names a companion level "Niveau 80" whether or not it is the last one.
export function standingLabel(standing, { notStarted = 'Non commencée' } = {}) {
    if (standing.started === false) {
        return notStarted;
    }
    if (standing.completed && LEVEL_NAME.test(standing.standing_name)) {
        return `${standing.standing_name} · max`;
    }

    return standing.standing_name;
}
