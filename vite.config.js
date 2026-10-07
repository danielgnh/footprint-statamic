import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import statamic from '@statamic/cms/vite-plugin';
import { tracker } from './vitest.config.js';

export default defineConfig({
    plugins: [
        statamic(),
        laravel({
            input: ['resources/js/addon.js', 'resources/css/addon.css'],
            publicDirectory: 'resources/dist',
        }),
        tailwindcss(),
    ],
    resolve: { alias: { '@footprint': tracker } },
});
