import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import { vi } from 'vitest';

export async function mountWithPlugins(component, options = {}) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: options.initialState || {},
        stubActions: options.stubActions !== false,
    });

    return mount(component, {
        global: {
            plugins: [pinia],
            stubs: options.stubs || {},
            ...options.global,
        },
        props: options.props,
        slots: options.slots,
        attachTo: options.attachTo,
    });
}

// Raw Tailwind palettes and gradients of the old theme: a migrated screen only uses tokens.
export const LEGACY_PALETTE = /\b(?:bg|text|border|ring|from|via|to|divide|placeholder|fill|stroke|outline|decoration|accent|shadow)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)(?:-\d+)?(?:\/\d+)?\b|\bbg-(?:linear|gradient)-to-|\bbg-clip-text\b/;
