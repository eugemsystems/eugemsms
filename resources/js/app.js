import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

/**
 * Alpine.js is intentionally NOT imported/started here. Livewire 4 ships
 * its own bundled Alpine instance and auto-injects it on every page
 * (`inject_assets` in config/livewire.php) — per Livewire's own
 * troubleshooting guide ("Multiple instances of Alpine"), `wire:click`/
 * `wire:model`/etc. are implemented on top of Alpine's own directive
 * system, so a second, independently-started Alpine instance (as this
 * file used to `import Alpine from 'alpinejs'; Alpine.start();`) races
 * Livewire's bundled one to bind DOM elements. Whichever instance wins
 * that race for a given element determines whether it actually has
 * Livewire's directives attached — losing the race is silent (no
 * thrown error most of the time), which is why the symptom looked like
 * "this click does nothing" on an effectively random subset of elements,
 * fixed only by a hard reload (which re-runs the race, sometimes
 * luckily). `x-data`/`x-show`/etc. used declaratively in Blade views
 * still work fine off Livewire's own Alpine — nothing else in this file
 * called an `Alpine.*` API, so removing the import/start is a pure fix
 * with no lost functionality.
 */

/**
 * Light / dark / system theme. For a signed-in user this is sourced from
 * `users.theme` (Settings > Appearance persists it there — see
 * App\Livewire\Settings\Appearance) and mirrored to localStorage only so
 * the very first paint of the next request has no flash; localStorage is
 * never the source of truth once a user is authenticated. Bootstrap 5.3's
 * `data-bs-theme` attribute drives the actual palette switch — see the
 * inline pre-paint script in partials/head.blade.php for the flash-free
 * initial value.
 */
const THEME_STORAGE_KEY = 'serp-theme';

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolvedTheme(preference) {
    return preference === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : preference;
}

function swapThemedImages() {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    document.querySelectorAll('[data-light-src][data-dark-src]').forEach((img) => {
        img.src = dark ? img.dataset.darkSrc : img.dataset.lightSrc;
    });
}

function applyStoredTheme() {
    const stored = localStorage.getItem(THEME_STORAGE_KEY) || 'system';
    document.documentElement.setAttribute('data-bs-theme', resolvedTheme(stored));
    swapThemedImages();
}

window.applySerpTheme = function applySerpTheme(preference) {
    localStorage.setItem(THEME_STORAGE_KEY, preference);
    applyStoredTheme();
};

document.addEventListener('DOMContentLoaded', swapThemedImages);

/**
 * `wire:navigate` swaps the page without a full reload, so the pre-paint
 * inline script in partials/head.blade.php (which only runs on a real
 * document load) never re-fires — and Livewire's own DOM morph does not
 * reliably preserve the `data-bs-theme` attribute on <html>, since it was
 * added by JS rather than present in the server-rendered markup for that
 * page. Left alone, this makes the theme flip to Bootstrap's light default
 * (or whatever the previous page happened to leave behind) on every
 * internal navigation. Re-apply the persisted preference — mirrored to
 * localStorage on every real load and on every explicit theme change —
 * after each `wire:navigate` transition so it stays consistent.
 */
document.addEventListener('livewire:navigated', applyStoredTheme);

/**
 * Mobile sidebar toggle for the app shell (app/sidebar.blade.php).
 */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');

    if (toggle) {
        document.querySelector('.app-sidebar')?.classList.toggle('is-open');

        return;
    }

    if (!event.target.closest('.app-sidebar') && !event.target.closest('[data-sidebar-toggle]')) {
        document.querySelector('.app-sidebar')?.classList.remove('is-open');
    }
});

/**
 * Password visibility toggle used across auth forms
 * (`<span data-password-toggle="#field-id">`).
 */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');

    if (!toggle) {
        return;
    }

    const field = document.querySelector(toggle.dataset.passwordToggle);

    if (!field) {
        return;
    }

    const showing = field.type === 'text';
    field.type = showing ? 'password' : 'text';
    toggle.querySelector('i')?.classList.toggle('ri-eye-line', showing);
    toggle.querySelector('i')?.classList.toggle('ri-eye-off-line', !showing);
});

/**
 * Toasts — replaces Flux's server-dispatched `Flux::toast()`. A Livewire
 * component calls the `toast()` trait method (App\Concerns\Toasts), which
 * dispatches this browser event.
 */
const TOAST_VARIANT_CLASS = {
    success: 'text-bg-success',
    danger: 'text-bg-danger',
    warning: 'text-bg-warning',
    info: 'text-bg-info',
};

function renderToast({ text, variant = 'success' }) {
    const region = document.getElementById('serp-toast-region');

    if (!region || !text) {
        return;
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center border-0 ${TOAST_VARIANT_CLASS[variant] ?? TOAST_VARIANT_CLASS.success}`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    toastEl.querySelector('.toast-body').textContent = text;

    region.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    toast.show();
}

document.addEventListener('livewire:init', () => {
    Livewire.on('toast', renderToast);
});

/**
 * Multi-step wizard header (installer, `.bs-stepper` markup ported from
 * the TEMPLATE). Step *visibility* is driven by Livewire state; bs-stepper
 * is used only to render/animate the numbered header, not to gate content.
 */
document.addEventListener('livewire:navigated', initStepper);
document.addEventListener('DOMContentLoaded', initStepper);

function initStepper() {
    document.querySelectorAll('.bs-stepper').forEach((el) => {
        if (!el.dataset.stepperInitialised) {
            el.dataset.stepperInitialised = 'true';
        }
    });
}
