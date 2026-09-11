---
paths:
  - 'resources/js/**'
---

# Js

## Never import/start a separate Alpine instance — Livewire 4 already bundles one
`resources/js/app.js` used to `import Alpine from 'alpinejs'; Alpine.start();` alongside Livewire 4, which auto-injects its OWN bundled Alpine on every page (`inject_assets` in config/livewire.php, no `@livewireScriptConfig` opt-out present). This is the exact "Multiple instances of Alpine" conflict Livewire's own troubleshooting docs warn about — `wire:click`/`wire:model`/etc. are implemented on Alpine's directive system, so a second independently-started Alpine instance races Livewire's bundled one to bind DOM elements; losing that race for a given element is silent, which looked like "this click does nothing" on a random subset of elements, fixed only by a lucky reload.

Fix: don't import/start Alpine separately. `x-data`/`x-show`/etc. used declaratively in Blade views work fine off Livewire's own bundled Alpine. `window.bootstrap = bootstrap` (Bootstrap 5 JS) is unrelated and stays.
