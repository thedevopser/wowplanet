// The week of the account arrives computed and sorted from the server: the page only names
// what it shows.

// Mirror of RaidDifficulty, from the easiest to the hardest.
const DIFFICULTIES = Object.freeze(['LFR', 'NORMAL', 'HEROIC', 'MYTHIC']);

const DIFFICULTY_LABELS = Object.freeze({ LFR: 'LFR', NORMAL: 'Normal', HEROIC: 'Héroïque', MYTHIC: 'Mythique' });

// The words of the page, French unless the page gives its own.
const FRENCH = Object.freeze({
    difficulty: (difficulty) => DIFFICULTY_LABELS[difficulty] ?? difficulty,
    units: Object.freeze({
        raid: (count) => `${count} boss`,
        mythicPlus: (count) => `${count} donjon${count > 1 ? 's' : ''}`,
    }),
});

const REWARDS = Object.freeze({
    raid: (difficulty, labels) => labels.difficulty(difficulty),
    mythicPlus: (level) => `+${level}`,
});

export function difficultyLabel(difficulty, labels = FRENCH) {
    return labels.difficulty(difficulty);
}

export function hardestFirst(difficulties) {
    return [...difficulties].sort((first, second) => DIFFICULTIES.indexOf(second.difficulty) - DIFFICULTIES.indexOf(first.difficulty));
}

export function slotLabel(vault, slot, labels = FRENCH) {
    return slot.reached ? REWARDS[vault](slot.reward, labels) : labels.units[vault](slot.threshold);
}

export function nextSlot({ count, slots }) {
    const position = slots.findIndex((slot) => !slot.reached);

    return position === -1 ? null : { missing: slots[position].threshold - count, position: position + 1 };
}

export function unitOf(vault, count, labels = FRENCH) {
    return labels.units[vault](count);
}
