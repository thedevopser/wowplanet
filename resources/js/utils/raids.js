// Raid progress as the sheet shows it. Blizzard lists, for each difficulty, only the
// bosses already defeated: a boss never killed anywhere has no name, only a count.

// Difficulty colours borrow the item qualities, as players read them in game.
export const DIFFICULTIES = Object.freeze([
    { type: 'LFR', label: 'Outil Raids', short: 'LFR', quality: 'UNCOMMON' },
    { type: 'NORMAL', label: 'Normal', short: 'N', quality: 'RARE' },
    { type: 'HEROIC', label: 'Héroïque', short: 'H', quality: 'EPIC' },
    { type: 'MYTHIC', label: 'Mythique', short: 'M', quality: 'LEGENDARY' },
]);

const modeOf = (raid, type) => (raid.modes ?? []).find((mode) => mode.difficulty_type === type) ?? null;

export function difficultySummaries(raid) {
    return DIFFICULTIES.map((difficulty) => {
        const mode = modeOf(raid, difficulty.type);
        const completed = mode?.completed_count ?? 0;
        const total = mode?.total_count ?? 0;

        return { ...difficulty, started: mode !== null, completed, total, cleared: total > 0 && completed >= total };
    });
}

export function bossMatrix(raid) {
    const difficulties = DIFFICULTIES.map((difficulty) => difficulty.type).filter((type) => modeOf(raid, type));
    const modes = difficulties.map((type) => modeOf(raid, type));

    const names = [];
    for (const mode of modes.toSorted((a, b) => b.encounters.length - a.encounters.length)) {
        for (const encounter of mode.encounters) {
            if (!names.includes(encounter.name)) {
                names.push(encounter.name);
            }
        }
    }

    const rows = names.map((name) => ({
        name,
        kills: Object.fromEntries(modes.map((mode) => [
            mode.difficulty_type,
            mode.encounters.find((encounter) => encounter.name === name)?.last_kill_timestamp ?? null,
        ])),
    }));

    const bossCount = Math.max(0, ...modes.map((mode) => mode.total_count));

    return { difficulties, rows, unknownCount: Math.max(0, bossCount - names.length) };
}

// Blizzard numbers the instances as they come out: the highest id is the raid of the
// current tier, which comes first.
export function sortNewestFirst(raids) {
    return raids.toSorted((a, b) => b.instance_id - a.instance_id);
}
