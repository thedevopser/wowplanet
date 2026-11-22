import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createPinia } from 'pinia';
import { i18nVue } from 'laravel-vue-i18n';
import { eagerI18nOptions } from './i18n/translations';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
            return pages[`./pages/${name}.vue`];
        },
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });
            app.use(plugin);
            app.use(createPinia());
            app.use(i18nVue, eagerI18nOptions(page.props.locale));
            return app;
        },
    }),
);
