@props(['illustration' => 'login', 'title' => null])

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
