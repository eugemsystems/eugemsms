<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Maintenance mode') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Take the whole platform offline for deployments or emergency work.') }}</p>
    </div>

    <div class="card">
        <div class="card-body">
            @if ($isDown)
                <div class="alert alert-danger">
                    <strong>{{ __('The application is currently in maintenance mode.') }}</strong>
                    <p class="mb-0">{{ __('Everyone except the deploying team sees the maintenance page.') }}</p>
                </div>

                @if ($secret)
                    <p class="mb-3">
                        {{ __('Bypass link for the deploying team:') }}
                        <br>
                        <code>{{ url('/'.$secret) }}</code>
                    </p>
                @endif

                <button type="button" class="btn btn-success" wire:click="disable" wire:confirm="{{ __('Bring the application back online?') }}" wire:loading.attr="disabled">
                    <i class="ri ri-play-circle-line me-1"></i>{{ __('Bring back online') }}
                </button>
            @else
                <p class="mb-3">{{ __('The application is live. Enabling maintenance mode generates a one-time bypass link for the deploying team.') }}</p>
                <button type="button" class="btn btn-danger" wire:click="enable" wire:confirm="{{ __('Take the whole platform offline for maintenance?') }}" wire:loading.attr="disabled">
                    <i class="ri ri-tools-line me-1"></i>{{ __('Enable maintenance mode') }}
                </button>
            @endif
        </div>
    </div>
</div>
