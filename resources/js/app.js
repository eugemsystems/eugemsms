import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';

window.bootstrap = bootstrap;

Alpine.start();

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

window.applySerpTheme = function applySerpTheme(preference) {
    localStorage.setItem(THEME_STORAGE_KEY, preference);
    document.documentElement.setAttribute('data-bs-theme', resolvedTheme(preference));
    swapThemedImages();
};

document.addEventListener('DOMContentLoaded', swapThemedImages);

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
