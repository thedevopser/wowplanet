// @vitest-environment node
import { describe, it, expect, vi } from 'vitest';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        url: '/base-de-donnees/montures',
        props: { subCategories: { mounts: [{ slug: 'draconique', name: 'Draconique', count: 42 }] } },
    }),
    router: { on: () => () => {} },
}));

import DatabaseLayout from './DatabaseLayout.vue';

describe('server-side rendering of the database layout', () => {
    it('sends the active section already unfolded, with its sub-category links', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(DatabaseLayout) }));
        const unfolded = html.match(/aria-expanded="true"[^>]*>[\s\S]*?<\/button>/g) ?? [];

        expect(unfolded).toHaveLength(1);
        expect(unfolded[0]).toContain('Montures');
        expect(html).toContain('href="/base-de-donnees/montures/draconique"');
    });
});
