<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Migrations') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Set up the database schema for this installation.') }}</p>
    </div>

    @if (! $hasRun)
        <button type="button" class="btn btn-primary d-grid w-100 mb-4" wire:click="runMigrations" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="runMigrations">{{ __('Run migrations') }}</span>
            <span wire:loading wire:target="runMigrations">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                {{ __('Running…') }}
            </span>
        </button>
    @else
        <div class="alert {{ $succeeded ? 'alert-success' : 'alert-danger' }} d-flex align-items-center gap-2 mb-4">
            <i class="ri {{ $succeeded ? 'ri-checkbox-circle-line' : 'ri-error-warning-line' }}"></i>
            {{ $message }}
        </div>

        @if (! $succeeded)
            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 mb-4" wire:click="runMigrations">
                <i class="ri ri-refresh-line"></i> {{ __('Retry') }}
            </button>
        @endif
    @endif

    <button type="button" class="btn btn-primary d-grid w-100" wire:click="continue" @disabled(! $succeeded)>
        {{ __('Continue') }}
    </button>
</div>
