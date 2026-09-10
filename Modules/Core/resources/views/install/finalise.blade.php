<div class="text-center">
    @if (! $finalised)
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle mb-3" style="width:3.5rem;height:3.5rem;">
            <i class="ri ri-flag-line fs-3 text-primary"></i>
        </div>
        <h4 class="mb-1">{{ __('Ready to finish') }}</h4>
        <p class="text-body-secondary mb-4">
            {{ __('This writes the installation lock and caches the configuration. The installer becomes unreachable once this completes.') }}
        </p>

        <button type="button" class="btn btn-primary d-grid w-100" wire:click="finalise" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="finalise">{{ __('Finish installation') }}</span>
            <span wire:loading wire:target="finalise">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                {{ __('Finishing…') }}
            </span>
        </button>
    @else
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle mb-3" style="width:3.5rem;height:3.5rem;">
            <i class="ri ri-checkbox-circle-line fs-3 text-success"></i>
        </div>
        <h4 class="mb-1">{{ __('Installation complete') }}</h4>
        <p class="text-body-secondary mb-1">
            {{ __('sERP is ready. Sign in with the administrator account you just created.') }}
        </p>
        @if ($installationUuid)
            <p class="small text-body-secondary mb-4">
                {{ __('Installation ID:') }} <code>{{ $installationUuid }}</code>
            </p>
        @endif

        <a href="{{ route('login') }}" class="btn btn-primary d-grid w-100">
            {{ __('Go to login') }}
        </a>
    @endif
</div>
