import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

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
