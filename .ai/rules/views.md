---
paths:
  - 'resources/views/**'
---

# Views

## No Flux — Bootstrap 5 from TEMPLATE/html-laravel everywhere
Livewire Flux was removed entirely (composer + npm) per explicit user directive on 2026-09-08. Every Blade view — including the Fortify/Livewire-starter-kit auth pages (login, register, forgot/reset-password, two-factor-challenge, verify-email, confirm-password) and the settings pages (profile, security, delete-user-form, appearance, two-factor/recovery-codes) — is hand-built Bootstrap 5 markup adapted from `TEMPLATE/html-laravel/starter-kit/resources/views/content/authentications/*` (form-floating inputs, `input-group-merge` password toggles, RemixIcon `ri-*` classes, card-based auth wrapper). This applies project-wide, not just the admin/Livewire panel .claude/rules/livewire-admin-ui.md already covered.

Build system: `resources/css/app.scss` (renamed from app.css) imports Bootstrap SCSS with variable overrides ($primary: #696cff etc.) + `remixicon/fonts/remixicon.css`, no Tailwind. `resources/js/app.js` bundles Bootstrap JS + Alpine.js (kept — used independently of Flux for local UI state: two-factor-challenge OTP/recovery toggle, security-settings clipboard copy, recovery-codes show/hide) + the theme toggle (`data-bs-theme`, `localStorage['serp-theme']`, buttons tagged `data-theme-option="light|dark|system"`) + a toast renderer (`Livewire.on('toast', ...)` into `#serp-toast-region`) + a generic password-visibility toggle (`data-password-toggle="#field-id"`).

Toasts: `Flux::toast()` is replaced by the `App\Concerns\Toasts` trait — call `$this->toast($text, $variant = 'success')` from any Livewire component (see Profile.php, Security.php).

Modals: Flux's `<flux:modal>` is replaced by a hand-rolled pattern — a Livewire boolean property (e.g. `$showModal`) gates `@if ($showModal) <div class="modal show d-block" style="background:rgba(0,0,0,.5)">...</div> @endif` in the Blade view. No `bootstrap.Modal()` JS instance is used; visibility is pure server-driven Blade conditional + CSS classes, so it stays in sync with Livewire state with zero JS glue.

Layouts: `resources/views/layouts/auth/simple.blade.php` (card-centered auth wrapper) and `resources/views/layouts/app/sidebar.blade.php` (vertical-menu app shell, mobile-collapsible via `.app-sidebar.is-open` + `[data-sidebar-toggle]`) are the only two layout shells now — `auth/card.blade.php`, `auth/split.blade.php`, and `app/header.blade.php` (unused Flux-only alternates) were deleted.

## Auth pages are literal TEMPLATE ports, not adaptations
Follow-up to "No Flux — Bootstrap 5 from TEMPLATE/html-laravel everywhere" (2026-09-08): the user explicitly required the auth pages to look EXACTLY like TEMPLATE/html-laravel's actual pages, not a simplified adaptation. All of `resources/views/livewire/auth/*.blade.php` are now literal structural ports of `TEMPLATE/html-laravel/full-version/resources/views/content/authentications/auth-*-basic.blade.php` (login, register, forgot-password, reset-password, two-steps→two-factor-challenge, verify-email) — same `authentication-wrapper authentication-basic` / `authentication-inner` / `card p-md-7 p-1` / `app-brand` structure, same `form-floating`/`input-group-merge` field markup, same per-page illustration mask image faded bottom-left. Only functionally-fake decorative elements (the template's dead social-login icons) were dropped; everything else — including the exact heading/copy style (`Welcome to :app! 👋`, emoji headings) — was kept.

Assets: `public/assets/img/illustrations/auth-basic-{login,register,forgot-password,reset-password}-mask-{light,dark}.png` and `public/favicon.ico` are copied verbatim from `TEMPLATE/html-laravel/full-version/public/assets/img/...` and `starter-kit/public/assets/img/favicon/favicon.ico`. `x-app-logo-icon` is the TEMPLATE's actual brand SVG mark (from `_partials/macros.blade.php`), not a placeholder — every logo/favicon in the app must come from this same source, never a custom mark.

CSS ported (not the TEMPLATE's full `$prefix`-based Bootstrap-extended SCSS fork, which isn't imported — hand-equivalent rules in `resources/css/app.scss` instead): `.authentication-wrapper`/`.authentication-basic`/`.authentication-inner`/`.authentication-image`, `.app-brand`/`.app-brand-link`/`.app-brand-text`, `.divider`/`.divider-text`, `.input-group-merge` (seamless field+addon border), `.auth-input-wrapper .auth-input` (2FA OTP boxes).

Theme (light/dark/system) is no longer client-only: `users.theme` column is the source of truth once authenticated (`App\Livewire\Settings\Appearance::updateTheme()`), mirrored to `localStorage['serp-theme']` only for guest pages and flash-free pre-paint (see the inline script in `partials/head.blade.php` — must use `@if(auth()->check()) ... @else ... @endif`, never `@auth ... @else ... @endauth`, Blade's `@auth` directive has no `@else` clause and silently drops both branches). `window.applySerpTheme(preference)` in `resources/js/app.js` is the one function that applies a theme + swaps `[data-light-src]/[data-dark-src]` themed images (the auth illustrations).

Gotcha: an anonymous Blade component that declares `@props([...])` only exposes the LISTED keys as variables — an attribute passed in but not declared in `@props` (e.g. `:title=`) is silently dropped, not implicitly available like on a component with no `@props()` call at all. Every layout component taking `:title` must list `'title' => null` in its own `@props`.
