import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.{test,spec}.js'],
        setupFiles: ['resources/js/tests/setup.js'],
        globals: true,
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.{js,vue}'],
            exclude: [
                'resources/js/**/*.{test,spec}.js',
                'resources/js/tests/**',
                'resources/js/app.js',
                'resources/js/bootstrap.js',
                'resources/js/inertia.js',
                'resources/js/ssr.js',
            ],
            reporter: ['text', 'text-summary', 'html'],
            reportsDirectory: 'coverage/js',
            thresholds: {
                lines: 70,
                branches: 70,
                statements: 70,
                functions: 70,
            },
        },
    },
    resolve: {
        // Exact match only, as in vite.config.js: a prefix alias would also capture vue/server-renderer.
        alias: [
            {
                find: /^vue$/,
                replacement: path.resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
            },
        ],
    },
});
