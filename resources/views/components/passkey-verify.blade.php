@props([
    'optionsRoute' => 'passkey.login-options',
    'submitRoute' => 'passkey.login',
    'label' => __('Sign in with a passkey'),
    'loadingLabel' => __('Authenticating...'),
    'separator' => __('Or continue with email'),
])

@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    class="mb-4"
    x-data="{
        supported: false,
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        init() {
            this.updateSupport();

            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async verify() {
            this.loading = true;
            this.error = null;
            try {
                const response = await window.Passkeys.verify({
                    routes: {
                        options: '{{ route($optionsRoute) }}',
                        submit: '{{ route($submitRoute) }}',
                    },
                });
                Livewire.navigate(response.redirect || '/dashboard');
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
    }"
>
    <template x-if="supported">
        <div>
            <div class="d-grid gap-2">
                <button
                    type="button"
                    class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-1 w-100"
                    x-on:click="verify()"
                    x-bind:disabled="loading"
                >
                    <i class="ri ri-fingerprint-line"></i>
                    <span x-show="!loading">{{ $label }}</span>
                    <span x-show="loading" x-cloak>{{ $loadingLabel }}</span>
                </button>
                <p x-show="error" x-text="error" x-cloak class="small text-center text-danger mb-0"></p>
            </div>

            <div class="divider my-4 text-center position-relative">
                <hr class="position-absolute top-50 start-0 w-100">
                <span class="position-relative bg-body px-2 text-body-secondary small text-uppercase">
                    {{ $separator }}
                </span>
            </div>
        </div>
    </template>
</div>
