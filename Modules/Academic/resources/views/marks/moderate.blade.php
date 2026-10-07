<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $assessment->title }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Max mark') }}: {{ $assessment->max_mark }} — <span class="badge text-bg-secondary">{{ ucfirst($assessment->status) }}</span></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('Mark distribution') }}</div>
        <div class="card-body">
            @if ($distribution['count'] === 0)
                <p class="text-body-secondary mb-0">{{ __('No non-absent marks recorded yet.') }}</p>
            @else
                <div class="row text-center mb-3">
                    <div class="col">
                        <div class="fs-5">{{ $distribution['count'] }}</div>
                        <div class="text-body-secondary small">{{ __('Marked') }}</div>
                    </div>
                    <div class="col">
                        <div class="fs-5">{{ number_format($distribution['average'], 1) }}%</div>
                        <div class="text-body-secondary small">{{ __('Average') }}</div>
                    </div>
                    <div class="col">
                        <div class="fs-5">{{ number_format($distribution['min'], 1) }}%</div>
                        <div class="text-body-secondary small">{{ __('Lowest') }}</div>
                    </div>
                    <div class="col">
                        <div class="fs-5">{{ number_format($distribution['max'], 1) }}%</div>
                        <div class="text-body-secondary small">{{ __('Highest') }}</div>
                    </div>
                    <div class="col">
                        <div class="fs-5">{{ number_format($distribution['std_dev'], 1) }}</div>
                        <div class="text-body-secondary small">{{ __('Std. deviation') }}</div>
                    </div>
                </div>

                @if ($distribution['outliers'] !== [])
                    <p class="fw-semibold mb-1">{{ __('Outliers (more than 2 std. deviations from the mean)') }}</p>
                    <ul class="mb-0">
                        @foreach ($distribution['outliers'] as $outlier)
                            <li>{{ $outlier['student'] }} — {{ number_format($outlier['percent'], 1) }}%</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-body-secondary mb-0">{{ __('No outliers.') }}</p>
                @endif
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Moderation sign-off') }}</div>
        <div class="card-body">
            @if ($assessment->status === 'submitted')
                <div class="mb-3">
                    <label class="form-label">{{ __('Moderation note (optional)') }}</label>
                    <textarea class="form-control" rows="3" wire:model="moderationNote"></textarea>
                </div>
                <button type="button" class="btn btn-primary" wire:click="moderate" wire:confirm="{{ __('Sign off on this assessment as moderated?') }}">{{ __('Moderate') }}</button>
            @elseif ($assessment->status === 'moderated')
                <p class="mb-1">{{ __('Moderated') }} {{ $assessment->moderated_at?->diffForHumans() }}.</p>
                @if ($assessment->moderation_note)
                    <p class="text-body-secondary mb-0">{{ $assessment->moderation_note }}</p>
                @endif
            @else
                <p class="text-body-secondary mb-0">{{ __('An assessment must be submitted before it can be moderated.') }}</p>
            @endif
        </div>
    </div>
</div>
