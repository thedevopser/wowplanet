import { flushPromises, mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import { vi } from 'vitest';
import { i18nVue, reset as resetLoadedLanguages } from 'laravel-vue-i18n';
import { eagerI18nOptions } from '../i18n/translations.js';

export async function mountWithPlugins(component, options = {}) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: options.initialState || {},
        stubActions: options.stubActions !== false,
    });

    // Loaded languages are cached on the class: a language loaded by an earlier test would be
    // applied before the new instance renders, leaving the keys it reads untranslated.
    resetLoadedLanguages();
    const wrapper = mount(component, {
        global: {
            plugins: [pinia, [i18nVue, eagerI18nOptions(options.locale)]],
            stubs: options.stubs || {},
            ...options.global,
        },
        props: options.props,
        slots: options.slots,
        attachTo: options.attachTo,
    });
    await flushPromises();

    return wrapper;
}

// Raw Tailwind palettes and gradients of the old theme: a migrated screen only uses tokens.
export const LEGACY_PALETTE = /\b(?:bg|text|border|ring|from|via|to|divide|placeholder|fill|stroke|outline|decoration|accent|shadow)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)(?:-\d+)?(?:\/\d+)?\b|\bbg-(?:linear|gradient)-to-|\bbg-clip-text\b/;
