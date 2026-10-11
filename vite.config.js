import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Downloaded at build time and served from our own domain: no third-party font requests.
            fonts: [
                google('Archivo', { weights: [400, 600, 800], subsets: ['latin', 'latin-ext'] }),
                google('IBM Plex Sans Arabic', { weights: [400, 600, 700], subsets: ['arabic'] }),
                google('Reem Kufi', { weights: [700], subsets: ['arabic'], preload: false }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': '/resources/js' },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
