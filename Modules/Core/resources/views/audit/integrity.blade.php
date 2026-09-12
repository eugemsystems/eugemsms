<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('audit.explorer', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Integrity dashboard') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Last run, status, and failures per check.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="runChecks" wire:loading.attr="disabled">
            <i class="ri ri-play-line me-1"></i>{{ __('Run now') }}
        </button>
    </div>

    <div class="row g-3">
        @forelse ($checks as $check)
            <div class="col-md-6 col-lg-4" wire:key="check-{{ $check['type'] }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">{{ \Illuminate\Support\Str::headline($check['type']) }}</h6>
                            @if ($check['lastRun'])
                                <span class="badge text-bg-{{ $check['lastRun']->passed() ? 'success' : 'danger' }}">
                                    {{ \Illuminate\Support\Str::headline($check['lastRun']->status) }}
                                </span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Never run') }}</span>
                            @endif
                        </div>
                        @if ($check['lastRun'])
                            <p class="small text-body-secondary mb-1">{{ __('Ran :time', ['time' => $check['lastRun']->ran_at->diffForHumans()]) }}</p>
                            <p class="small mb-0">
                                {{ __(':checked checked, :failed failure(s)', ['checked' => $check['lastRun']->records_checked ?? 0, 'failed' => $check['lastRun']->failures_found]) }}
                            </p>
                        @else
                            <p class="small text-body-secondary mb-0">{{ __('Click "Run now" to execute this check.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <p class="text-body-secondary">{{ __('No integrity checks are registered yet.') }}</p>
            </div>
        @endforelse
    </div>
</div>
