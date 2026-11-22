import './bootstrap';

// iconizeLinks: false — on garde nos propres icônes (CollectionIcon, équipement…)
// et les tooltips au survol, sans la petite icône inline injectée par Wowhead
// (doublon sur les collections, trop petite ailleurs).
window.whTooltips = { colorLinks: true, iconizeLinks: false, renameLinks: false, locale: 'fr' };

import { createApp, h, nextTick } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { getActiveLanguage, i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { browserI18nOptions } from './i18n/translations';
import { useAuthGuard } from './composables/useAuthGuard';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.use(createPinia());

        // Après Pinia : la garde résout le store des personnages, qui exige une
        // instance active. Elle n'est pas posée dans ssr.js — un rendu serveur n'a
        // pas de session à expirer, et son axios est partagé entre les requêtes.
        useAuthGuard();

        app.use(i18nVue, browserI18nOptions(props.initialPage.props.locale, () => app.mount(el)));
    },
    // Only a visit longer than the delay shows the bar: a quick one stays silent.
    progress: { color: 'var(--wp-accent)', delay: 150, showSpinner: false },
});

// Rafraîchit les tooltips Wowhead après chaque navigation Inertia
// (remplace l'ancien router.afterEach de Vue Router).
router.on('navigate', (event) => {
    const locale = event.detail.page.props.locale;
    if (locale && locale !== getActiveLanguage()) {
        loadLanguageAsync(locale);
    }
    nextTick(() => window.$WowheadPower?.refreshLinks());
});
