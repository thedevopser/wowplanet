// The week of the account arrives computed and sorted from the server: the page only names
// what it shows.

// Mirror of RaidDifficulty, from the easiest to the hardest.
const DIFFICULTIES = Object.freeze(['LFR', 'NORMAL', 'HEROIC', 'MYTHIC']);

const DIFFICULTY_LABELS = Object.freeze({ LFR: 'LFR', NORMAL: 'Normal', HEROIC: 'Héroïque', MYTHIC: 'Mythique' });

const UNITS = Object.freeze({
    raid: (count) => `${count} boss`,
    mythicPlus: (count) => `${count} donjon${count > 1 ? 's' : ''}`,
});

const REWARDS = Object.freeze({
    raid: (difficulty) => difficultyLabel(difficulty),
    mythicPlus: (level) => `+${level}`,
});

export function difficultyLabel(difficulty) {
    return DIFFICULTY_LABELS[difficulty] ?? difficulty;
}

export function hardestFirst(difficulties) {
    return [...difficulties].sort((first, second) => DIFFICULTIES.indexOf(second.difficulty) - DIFFICULTIES.indexOf(first.difficulty));
}

export function slotLabel(vault, slot) {
    return slot.reached ? REWARDS[vault](slot.reward) : UNITS[vault](slot.threshold);
}

export function nextSlot({ count, slots }) {
    const position = slots.findIndex((slot) => !slot.reached);

    return position === -1 ? null : { missing: slots[position].threshold - count, position: position + 1 };
}

export function unitOf(vault, count) {
    return UNITS[vault](count);
}
