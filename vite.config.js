import fs from 'node:fs';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';
import { loadEnv } from 'vite';

// Full env (not just VITE_-prefixed) so APP_URL/VITE_TLS_* are readable here too.
const env = loadEnv(process.env.NODE_ENV ?? 'development', process.cwd(), '');
const vitePort = Number(env.VITE_PORT ?? 5173);

// `--host 0.0.0.0` (needed so the container accepts connections from the host) makes Vite
// resolve its own "local" URL to the literal bind address 0.0.0.0, which laravel-vite-plugin
// then writes into public/hot verbatim -- unreachable from the browser, and still http even
// when the app is served over https (mixed content blocks the stylesheet). `server.origin`
// overrides the URL laravel-vite-plugin derives, so pin it to the public-facing host/scheme
// (APP_URL) on the dev server's own port instead of trusting the resolved bind address.
const appUrl = (() => {
    try {
        return new URL(env.APP_URL);
    } catch {
        return null;
    }
})();
const origin = appUrl ? `${appUrl.protocol}//${appUrl.hostname}:${vitePort}` : undefined;

// Only set when docker-compose.traefik.yml mounts an mkcert cert into the vite container
// (see docs/DOCKER.md); without it the dev server stays plain http, as before.
const tls = env.VITE_TLS_CERT && env.VITE_TLS_KEY
    ? { cert: fs.readFileSync(env.VITE_TLS_CERT), key: fs.readFileSync(env.VITE_TLS_KEY) }
    : undefined;

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.scss',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                bunny('Public Sans', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
    ]),
    server: {
        cors: true,
        origin,
        https: tls,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
    css: {
        preprocessorOptions: {
            // Bootstrap 5.3.x's own SCSS source still uses legacy Sass
            // syntax (@import, legacy if()/color functions like
            // red()/green()/blue(), global-builtin mix()) — this is an
            // upstream Bootstrap issue, not ours, and floods `npm run dev`
            // with hundreds of Dart Sass deprecation warnings. Silence only
            // these known-upstream categories until Bootstrap ships a fixed
            // SCSS source; do not silence deprecations broadly.
            scss: {
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
});
