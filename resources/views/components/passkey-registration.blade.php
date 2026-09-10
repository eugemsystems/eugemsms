@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    x-data="{
        supported: false,
        showForm: false,
        name: '',
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        getDefaultPasskeyName() {
            const ua = navigator.userAgent;

            const browser = [
                { pattern: /Edg|Edge/, name: 'Edge' },
                { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
                { pattern: /Firefox|FxiOS/, name: 'Firefox' },
                { pattern: /Chrome|CriOS/, name: 'Chrome' },
                { pattern: /Safari/, name: 'Safari' },
            ].find(({ pattern }) => pattern.test(ua))?.name;

            const os = [
                { pattern: /iPhone/, name: 'iPhone' },
                { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
                { pattern: /Android/, name: 'Android' },
                { pattern: /Mac/, name: 'Mac' },
                { pattern: /Windows/, name: 'Windows' },
            ].find(({ pattern }) => pattern.test(ua))?.name;

            return [browser, os].filter(Boolean).join(' on ') || '';
        },
        init() {
            this.name = this.getDefaultPasskeyName();
            this.updateSupport();

            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async register() {
            if (!this.name.trim()) return;

            this.loading = true;
            this.error = null;

            try {
                await window.Passkeys.register({ name: this.name });
                this.name = '';
                this.showForm = false;
                await $wire.loadPasskeys();
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
        cancel() {
            this.showForm = false;
            this.name = '';
            this.error = null;
        },
    }"
>
    <template x-if="!supported">
        <p class="text-body-secondary small mb-0">{{ __('Passkeys are not supported in this browser.') }}</p>
    </template>

    <template x-if="supported && !showForm">
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" x-on:click="showForm = true">
            <i class="ri ri-add-line"></i> {{ __('Add passkey') }}
        </button>
    </template>

    <template x-if="supported && showForm">
        <div class="border rounded p-3 bg-body-tertiary">
            <div class="form-floating mb-2">
                <input
                    type="text"
                    class="form-control"
                    id="passkey-name"
                    x-model="name"
                    placeholder="{{ __('e.g., MacBook Pro, iPhone') }}"
                    x-on:keydown.enter.prevent="register()"
                    x-ref="passkeyNameInput"
                    x-init="$nextTick(() => $refs.passkeyNameInput?.focus())"
                >
                <label for="passkey-name">{{ __('Passkey name') }}</label>
            </div>
            <p class="small text-body-secondary">{{ __('Give this passkey a name to help you identify it later.') }}</p>

            <p x-show="error" x-text="error" x-cloak class="small text-danger"></p>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" x-on:click="register()" x-bind:disabled="loading || !name.trim()">
                    <span x-show="!loading">{{ __('Register passkey') }}</span>
                    <span x-show="loading" x-cloak>{{ __('Registering...') }}</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" x-on:click="cancel()">
                    {{ __('Cancel') }}
                </button>
            </div>
        </div>
    </template>
</div>
