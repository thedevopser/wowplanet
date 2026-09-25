import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/inertia.js'],
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        origin: 'https://wowplanet.dev.local',
        hmr: {
            host: 'wowplanet.dev.local',
            protocol: 'wss',
            clientPort: 443,
            path: '/@vite/ws',
        },
    },
    resolve: {
        alias: [
            {
                find: /^vue$/,
                replacement: path.resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
            },
        ],
    },
});
