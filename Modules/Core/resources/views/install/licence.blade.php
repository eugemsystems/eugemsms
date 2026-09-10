<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Licence') }}</h4>
        <p class="text-body-secondary mb-0">
            {{ __('Enter your licence key, or continue without one — sERP installs in a 14-day grace period and never blocks installation on a licence problem.') }}
        </p>
    </div>

    <form wire:submit="continue">
        <div class="form-floating form-floating-outline mb-4">
            <input type="text" class="form-control @error('licenceKey') is-invalid @enderror" id="licenceKey" wire:model="licenceKey" placeholder="SERP-XXXX-XXXX-XXXX">
            <label for="licenceKey">{{ __('Licence key (optional)') }}</label>
            @error('licenceKey')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary d-grid w-100">
            {{ $licenceKey !== '' ? __('Activate') : __('Continue without a licence') }}
        </button>
    </form>
</div>
