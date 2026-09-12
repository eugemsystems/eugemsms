<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Two-factor authentication') }}</h4>
        <p class="text-body-secondary mb-0">
            @if ($isRequired && ! $isConfirmed)
                {{ __('Your role requires two-factor authentication — you must finish setting it up before you can continue.') }}
            @else
                {{ __('Add an extra layer of security to your account using an authenticator app.') }}
            @endif
        </p>
    </div>

    <div class="card" style="max-width: 32rem;">
        <div class="card-body">
            @if (! $isEnabled)
                <p>{{ __('Scan a QR code with your authenticator app (Google Authenticator, Authy, etc.) to get started.') }}</p>
                <button type="button" class="btn btn-primary" wire:click="enable">
                    {{ __('Enable two-factor authentication') }}
                </button>
            @elseif (! $isConfirmed)
                <div class="text-center mb-4">
                    {!! $qrCodeSvg !!}
                </div>

                <form wire:submit="confirm">
                    <div class="form-floating form-floating-outline mb-3">
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            class="form-control @error('code') is-invalid @enderror"
                            id="two-factor-code"
                            wire:model="code"
                            placeholder=" "
                        >
                        <label for="two-factor-code">{{ __('Enter the 6-digit code from your app') }}</label>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ __('Confirm') }}
                    </button>
                </form>
            @else
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="ri ri-shield-check-line text-success fs-4"></i>
                    <span>{{ __('Two-factor authentication is enabled.') }}</span>
                </div>

                @if ($showRecoveryCodes && $recoveryCodes)
                    <div class="alert alert-warning">
                        <p class="fw-medium mb-2">{{ __('Save these recovery codes somewhere safe — each can be used once if you lose access to your authenticator app.') }}</p>
                        <pre class="mb-0 small">{{ implode("\n", $recoveryCodes) }}</pre>
                    </div>
                @endif

                @unless ($isRequired)
                    <button type="button" class="btn btn-outline-danger" wire:click="disable" wire:confirm="{{ __('Disable two-factor authentication?') }}">
                        {{ __('Disable two-factor authentication') }}
                    </button>
                @endunless
            @endif
        </div>
    </div>
</div>
