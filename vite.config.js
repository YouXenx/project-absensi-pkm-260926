import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/admin.css', 'resources/js/admin.js'],
            refresh: true,
            // Fonts of the Adminator theme, downloaded at build time and served from /build (no Google Fonts request).
            // Only the weights used above the fold are preloaded.
            fonts: [
                google('Inter', {
                    weights: [400, 500, 600, 700],
                    preload: [{ weight: 400 }, { weight: 500 }],
                    optimizedFallbacks: false,
                }),
                google('Inter Tight', {
                    weights: [500, 600, 700],
                    preload: [{ weight: 700 }],
                    optimizedFallbacks: false,
                }),
                google('JetBrains Mono', {
                    weights: [400, 500],
                    preload: false,
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
