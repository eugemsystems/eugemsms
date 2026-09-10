<div>
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle mb-3" style="width:3.5rem;height:3.5rem;">
            <i class="ri ri-rocket-2-line fs-3 text-primary"></i>
        </div>
        <h4 class="mb-1">{{ __('Welcome to :app', ['app' => config('app.name')]) }}</h4>
        <p class="text-body-secondary mb-0">
            {{ __("This wizard will get sERP from a bare server to a running, licensed, first-school-configured system — no developer required.") }}
        </p>
    </div>

    <form wire:submit="continue">
        <div class="form-check mb-4">
            <input type="checkbox" class="form-check-input @error('termsAccepted') is-invalid @enderror" id="termsAccepted" wire:model="termsAccepted">
            <label class="form-check-label" for="termsAccepted">
                {{ __('I have read and accept the terms of service.') }}
            </label>
            @error('termsAccepted')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary d-grid w-100">
            {{ __('Get started') }}
        </button>
    </form>
</div>
