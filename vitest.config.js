import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// The overlay finds sections with the tracker's own rules, read from the
// installed footprint package, so the two can never disagree.
export const tracker = fileURLToPath(new URL('./vendor/danielgnh/footprint/resources/js/tracker', import.meta.url));

export default defineConfig({
    resolve: { alias: { '@footprint': tracker } },
    test: {
        environment: 'happy-dom',
        include: ['tests/js/**/*.test.js'],
    },
});
