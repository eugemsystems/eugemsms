<div class="border rounded-3 shadow-sm p-4" wire:cloak x-data="{ showRecoveryCodes: false }">
    <div class="d-flex align-items-center gap-2 mb-1">
        <i class="ri ri-lock-2-line"></i>
        <h6 class="mb-0">{{ __('2FA recovery codes') }}</h6>
    </div>
    <p class="text-body-secondary small">
        {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
    </p>

    <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-sm-between">
        <button
            type="button"
            class="btn btn-primary d-inline-flex align-items-center gap-1"
            x-show="!showRecoveryCodes"
            @click="showRecoveryCodes = true"
            aria-expanded="false"
            aria-controls="recovery-codes-section"
        >
            <i class="ri ri-eye-line"></i> {{ __('View recovery codes') }}
        </button>

        <button
            type="button"
            class="btn btn-primary d-inline-flex align-items-center gap-1"
            x-show="showRecoveryCodes"
            x-cloak
            @click="showRecoveryCodes = false"
            aria-expanded="true"
            aria-controls="recovery-codes-section"
        >
            <i class="ri ri-eye-off-line"></i> {{ __('Hide recovery codes') }}
        </button>

        @if (filled($recoveryCodes))
            <button
                type="button"
                class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                x-show="showRecoveryCodes"
                x-cloak
                wire:click="regenerateRecoveryCodes"
            >
                <i class="ri ri-refresh-line"></i> {{ __('Regenerate codes') }}
            </button>
        @endif
    </div>

    <div x-show="showRecoveryCodes" x-cloak id="recovery-codes-section" x-bind:aria-hidden="!showRecoveryCodes">
        <div class="mt-3">
            @error('recoveryCodes')
                <div class="alert alert-danger d-flex align-items-center gap-2">
                    <i class="ri ri-error-warning-line"></i> {{ $message }}
                </div>
            @enderror

            @if (filled($recoveryCodes))
                <div class="font-monospace small bg-body-tertiary rounded p-3 d-grid gap-1" role="list" aria-label="{{ __('Recovery codes') }}">
                    @foreach ($recoveryCodes as $code)
                        <div role="listitem" class="user-select-all" wire:loading.class="opacity-50">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
                <p class="text-body-secondary small mt-2 mb-0">
                    {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                </p>
            @endif
        </div>
    </div>
</div>
