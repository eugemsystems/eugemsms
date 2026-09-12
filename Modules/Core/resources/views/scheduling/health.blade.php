<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('System health') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Latest reading for every available check.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="runChecks" wire:loading.attr="disabled">
            <i class="ri ri-refresh-line me-1"></i>{{ __('Run now') }}
        </button>
    </div>

    <div class="row g-3">
        @forelse ($checks as $row)
            @php $latest = $row['latest']; @endphp
            <div class="col-md-6 col-lg-4" wire:key="check-{{ $row['key'] }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">{{ \Illuminate\Support\Str::headline($row['key']) }}</h6>
                            @if ($latest)
                                @php
                                    $badge = match ($latest->status) {
                                        'healthy' => 'success',
                                        'degraded' => 'warning',
                                        'unhealthy' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ \Illuminate\Support\Str::headline($latest->status) }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Not run yet') }}</span>
                            @endif
                        </div>
                        @if ($latest)
                            <p class="mb-1">{{ $latest->value ?? '—' }}</p>
                            @if ($latest->threshold)
                                <p class="small text-body-secondary mb-1">{{ $latest->threshold }}</p>
                            @endif
                            @if ($latest->message)
                                <p class="small text-body-secondary mb-1">{{ $latest->message }}</p>
                            @endif
                            <p class="small text-body-secondary mb-0">{{ $latest->checked_at->diffForHumans() }}</p>
                        @else
                            <p class="small text-body-secondary mb-0">{{ __('Click "Run now" to check.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-center text-body-secondary py-5">{{ __('No health checks are registered.') }}</div></div>
            </div>
        @endforelse
    </div>
</div>
