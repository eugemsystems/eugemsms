<div wire:poll.5s>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('My jobs') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Long-running work you started, refreshed automatically.') }}</p>
    </div>

    <div class="row g-3">
        @forelse ($jobs as $job)
            <div class="col-md-6" wire:key="job-{{ $job->id }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">{{ $job->title }}</h6>
                            @php
                                $badge = match ($job->status) {
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'cancelled' => 'secondary',
                                    'running' => 'info',
                                    default => 'warning',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ \Illuminate\Support\Str::headline($job->status) }}</span>
                        </div>

                        @if ($job->total_steps !== null)
                            <div class="progress mb-2" style="height: 6px;">
                                <div class="progress-bar" style="width: {{ $job->percentComplete() }}%"></div>
                            </div>
                            <p class="small text-body-secondary mb-1">{{ __(':completed / :total steps (:percent%)', ['completed' => $job->completed_steps, 'total' => $job->total_steps, 'percent' => $job->percentComplete()]) }}</p>
                        @endif

                        @if ($job->current_message)
                            <p class="small mb-1">{{ $job->current_message }}</p>
                        @endif

                        @if ($job->error)
                            <p class="small text-danger mb-1">{{ $job->error }}</p>
                        @endif

                        @if (in_array($job->status, ['queued', 'running'], true))
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" wire:click="cancel({{ $job->id }})" wire:confirm="{{ __('Cancel this job?') }}">
                                {{ __('Cancel') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-center text-body-secondary py-5">{{ __('No jobs are running right now.') }}</div></div>
            </div>
        @endforelse
    </div>
</div>
