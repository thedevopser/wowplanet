// Best Mythic+ runs of a character, as the Blizzard profile lists them: several runs of
// the same dungeon can come back, only the highest level of each is worth showing.

function bestPerDungeon(runs) {
    const best = new Map();
    for (const run of runs) {
        const known = best.get(run.dungeon_id);
        if (!known || run.level > known.level) {
            best.set(run.dungeon_id, run);
        }
    }

    return [...best.values()].toSorted((a, b) => b.level - a.level);
}

export function bestRunsByTiming(runs = []) {
    return {
        timed: bestPerDungeon((runs ?? []).filter((run) => run.is_timed)),
        untimed: bestPerDungeon((runs ?? []).filter((run) => !run.is_timed)),
    };
}

export function uniqueDungeonCount(runs = []) {
    return new Set((runs ?? []).map((run) => run.dungeon_id)).size;
}

export function formatRunDuration(ms) {
    const seconds = Math.floor(ms / 1000);

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

// One card per dungeon: its best run in time leads, and the best run of the other kind
// comes along, so that a higher key missed in time is never lost.
export function dungeonCards(runs = []) {
    const { timed, untimed } = bestRunsByTiming(runs);
    const dungeonIds = [...new Set([...timed, ...untimed].map((run) => run.dungeon_id))];

    return dungeonIds
        .map((id) => {
            const inTime = timed.find((run) => run.dungeon_id === id) ?? null;
            const late = untimed.find((run) => run.dungeon_id === id) ?? null;
            const lead = inTime ?? late;

            return { dungeon_id: id, name: lead.dungeon_name, lead, other: inTime ? late : null };
        })
        .toSorted((a, b) => b.lead.level - a.lead.level || a.name.localeCompare(b.name, 'fr'));
}

export function seasonStats(runs = []) {
    const { timed } = bestRunsByTiming(runs);

    return {
        dungeons: uniqueDungeonCount(runs),
        highestTimed: timed.length ? Math.max(...timed.map((run) => run.level)) : null,
        timedDungeons: timed.length,
    };
}
