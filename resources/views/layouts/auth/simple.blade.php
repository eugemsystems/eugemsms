@props(['illustration' => 'login', 'title' => null])

{{--
    Every auth screen (login, register, 2FA challenge, password
    reset/confirm, email verification) is deliberately plain Blade +
    a native <form method="POST">, not a Livewire component — see
    Book A CORE-05's own reasoning on the (now-removed) Login component's
    old docblock. But several of them still use Alpine (`x-data`) for
    client-side-only behaviour: the passkey "sign in" button
    (<x-passkey-verify>) and the 2FA screen's auto-advancing digit boxes
    and recovery-code toggle. Livewire bundles Alpine and injects it
    automatically, but ONLY on a request that actually rendered a
    Livewire component (`SupportAutoInjectedAssets::shouldInjectLivewireAssets()`)
    — none of these pages do, so without this, `x-data`/`x-on`/`x-show`
    are silently inert everywhere on this layout: the 2FA boxes never
    auto-advance (2026-09-13, user-reported: "I have to click each box"),
    and the passkey button does nothing. `forceAssetInjection()` is
    Livewire's own public API for exactly this — a page using its Alpine
    bundle without an actual Livewire component on it.
--}}
@php \Livewire\Livewire::forceAssetInjection() @endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <div class="position-relative">
            <div class="authentication-wrapper authentication-basic container-p-y p-4 p-sm-0">
                <div class="authentication-inner py-6">
                    <div class="card p-md-7 p-1">
                        <div class="app-brand justify-content-center mt-5">
                            <a href="{{ route('home') }}" class="app-brand-link gap-2" wire:navigate>
                                <x-app-logo-icon class="app-brand-logo demo" />
                                <span class="app-brand-text demo text-heading fw-semibold">{{ config('app.name', 'Laravel') }}</span>
                            </a>
                        </div>

                        <div class="card-body mt-1">
                            {{ $slot }}
                        </div>
                    </div>

                    <img
                        alt="mask"
                        src="{{ asset('assets/img/illustrations/auth-basic-'.$illustration.'-mask-light.png') }}"
                        class="authentication-image d-none d-lg-block"
                        data-light-src="{{ asset('assets/img/illustrations/auth-basic-'.$illustration.'-mask-light.png') }}"
                        data-dark-src="{{ asset('assets/img/illustrations/auth-basic-'.$illustration.'-mask-dark.png') }}"
                    />
                </div>
            </div>
        </div>

        <div id="serp-toast-region" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>
    </body>
</html>
