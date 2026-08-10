import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted brand fonts, limited to the weights the storefront uses.
            //
            // Preloading is off on purpose: Bunny splits each weight into ~7
            // unicode-range subsets, and `rel=preload` ignores unicode-range, so
            // preloading would eagerly pull ~250KB of subsets the page never
            // renders. The @font-face rules are inlined into <head> by @fonts, so
            // the browser still discovers them at parse time, and `display: swap`
            // means they never block the first paint.
            fonts: [
                bunny('Playfair Display', {
                    weights: [400, 600, 700],
                    subsets: ['latin'],
                    preload: false,
                }),
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                    subsets: ['latin'],
                    preload: false,
                }),
                // Arabic UI font. Self-hosted rather than pulled from the Google
                // Fonts CDN so it never blocks first render on AR pages.
                bunny('Cairo', {
                    weights: [400, 500, 700],
                    subsets: ['arabic', 'latin'],
                    preload: false,
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
