import { defineStore } from 'pinia';

export const TOAST_TONES = Object.freeze(['info', 'success', 'warning', 'error']);

export class InvalidToastError extends Error {
    constructor(toast) {
        super(`Invalid toast: ${JSON.stringify(toast)}`);
        this.name = 'InvalidToastError';
    }
}

const isNonEmptyString = (value) => typeof value === 'string' && value !== '';

function isValidAction(action) {
    return action === undefined || (isNonEmptyString(action?.label) && isNonEmptyString(action.href));
}

function isValid({ title, description = '', tone = 'info', action }) {
    return isNonEmptyString(title) && typeof description === 'string' && TOAST_TONES.includes(tone) && isValidAction(action);
}

// A Pinia store rather than module state: toasts may be pushed while rendering on the
// server, where each request has its own Pinia.
export const useToastStore = defineStore('toasts', {
    state: () => ({
        items: [],
        lastId: 0,
    }),

    actions: {
        show(toast) {
            if (!isValid(toast ?? {})) {
                throw new InvalidToastError(toast);
            }

            this.lastId += 1;
            this.items.push({
                id: this.lastId,
                title: toast.title,
                description: toast.description ?? '',
                tone: toast.tone ?? 'info',
                ...(toast.action ? { action: { label: toast.action.label, href: toast.action.href } } : {}),
            });

            return this.lastId;
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    },
});
