import { config } from '@vue/test-utils';
import { i18nVue } from 'laravel-vue-i18n';
import { eagerI18nOptions } from '../i18n/translations.js';

// Node exposes an experimental global localStorage accessor that stays undefined without
// --localstorage-file, and it takes precedence over the one happy-dom provides. Install
// happy-dom's own Storage on the globals so tests get a working implementation. Tests that
// opt into the node environment have no window and keep Node's globals.
if (typeof window !== 'undefined') {
    for (const key of ['localStorage', 'sessionStorage']) {
        Object.defineProperty(globalThis, key, {
            value: new globalThis.Storage(),
            configurable: true,
        });
    }
}

// Every mounted component can translate, in French unless mountWithPlugins asks otherwise.
config.global.plugins.push([i18nVue, eagerI18nOptions('fr')]);
