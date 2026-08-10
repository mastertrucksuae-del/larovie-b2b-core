import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';

const BUILD_DIR = 'public/build';

/**
 * Drop the legacy WOFF @font-face rules the fonts plugin emits.
 *
 * Bunny returns two rules per variant — one WOFF2, one WOFF — with identical
 * font-family, font-weight, font-style AND unicode-range, WOFF declared second.
 * Per the CSS Fonts spec the later matching rule wins, so browsers downloaded
 * the legacy format and the WOFF2 was never used at all: PageSpeed's network
 * tree showed inter-400.woff at 30.26 KiB rather than its 22 KiB WOFF2 twin.
 *
 * WOFF2 has been supported everywhere since 2016 (Chrome 36, Firefox 39,
 * Safari 10, Edge 14), so the fallback buys nothing. Removing it also halves
 * the @font-face CSS that `@fonts` inlines into every page's <head>.
 */
function woff2Only() {
    const stripWoff = (css) =>
        css
            .split(/(?=@font-face)/)
            .filter((block) => !/format\("woff"\)/.test(block))
            .join('');

    return {
        name: 'larovie:woff2-only',
        apply: 'build',
        closeBundle() {
            const manifestPath = path.join(BUILD_DIR, 'fonts-manifest.json');

            if (!fs.existsSync(manifestPath)) {
                return;
            }

            const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
            const before = JSON.stringify(manifest).length;

            for (const [family, css] of Object.entries(manifest.style?.familyStyles ?? {})) {
                manifest.style.familyStyles[family] = stripWoff(css);
            }

            if (Array.isArray(manifest.preloads)) {
                manifest.preloads = manifest.preloads.filter((p) => !p.file?.endsWith('.woff'));
            }

            fs.writeFileSync(manifestPath, JSON.stringify(manifest));

            // The standalone stylesheet is used when @fonts is called without aliases.
            const styleFile = manifest.style?.file;
            if (styleFile) {
                const stylePath = path.join(BUILD_DIR, styleFile);
                if (fs.existsSync(stylePath)) {
                    fs.writeFileSync(stylePath, stripWoff(fs.readFileSync(stylePath, 'utf8')));
                }
            }

            // Nothing references the .woff files now, so don't ship them.
            const assetsDir = path.join(BUILD_DIR, 'assets');
            let removed = 0;
            let freed = 0;
            for (const file of fs.readdirSync(assetsDir)) {
                if (file.endsWith('.woff')) {
                    freed += fs.statSync(path.join(assetsDir, file)).size;
                    fs.unlinkSync(path.join(assetsDir, file));
                    removed++;
                }
            }

            const after = JSON.stringify(manifest).length;
            console.log(
                `\x1b[32m✓\x1b[0m woff2-only: font CSS ${before} → ${after} bytes, ` +
                `removed ${removed} .woff files (${(freed / 1024).toFixed(0)} KiB)`
            );
        },
    };
}

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
        woff2Only(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
