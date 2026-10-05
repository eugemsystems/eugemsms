<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Head dashboard') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Each indicator is coloured against its target — green on target, amber to watch, red off target.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('insights.executive.board-pack', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Board pack') }}</a>
            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="sendDigest">{{ __('Send me today’s digest') }}</button>
        </div>
    </div>

    @if ($todaysDigest)
        @php $summary = $todaysDigest->content_summary; @endphp
        <div class="alert {{ ($summary['all_green'] ?? false) ? 'alert-success' : 'alert-warning' }} small">
            @if ($summary['all_green'] ?? false)
                {{ __('Today’s digest: everything is on target.') }}
            @else
                <strong>{{ __('Today’s digest — needs attention:') }}</strong>
                <ul class="mb-0">@foreach ($summary['exceptions'] as $exception) <li>{{ $exception['label'] }} ({{ $exception['status'] }}): {{ $exception['current_value'] }} {{ __('vs target') }} {{ $exception['target_value'] ?? '—' }}</li> @endforeach</ul>
            @endif
        </div>
    @endif

    @include('intelligence::executive.partials.kpi-tiles', ['tiles' => $tiles])

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">{{ __('Enrolment — three years') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($comparative as $row)
                        <li class="list-group-item d-flex justify-content-between" wire:key="cmp-{{ $row['year'] }}">
                            <span>{{ $row['year'] }}</span>
                            <span>{{ $row['studentCount'] !== null ? $row['studentCount'].' '.__('learners').' ('.$row['snapshotDate'].')' : __('no snapshot yet') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="card-footer small text-body-secondary">{{ __('From nightly warehouse snapshots, not a live count. A year shows “no snapshot yet” until the nightly rebuild has run.') }}</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">{{ __('Stock consumption anomalies') }}</div>
                <div class="card-body">
                    <div class="h3 mb-0">{{ $openAnomalies }}</div>
                    <div class="small text-body-secondary">{{ __('open anomalies detected by the stores module') }}</div>
                    @if ($anomaliesUrl) <a href="{{ $anomaliesUrl }}" class="small" wire:navigate>{{ __('Investigate') }} →</a> @endif
                </div>
            </div>
        </div>
    </div>

    @if ($widgets !== [])
        <h6 class="mt-4">{{ __('More from the school') }}</h6>
        <div class="row g-3">
            @foreach ($widgets as $widget)
                <div class="col-md-4" wire:key="w-{{ $loop->index }}"><div class="card"><div class="card-body"><div class="small text-body-secondary">{{ $widget['title'] }}</div>
                    @foreach ($widget['summary'] as $label => $value) <div class="small">{{ str_replace('_', ' ', $label) }}: <strong>{{ is_scalar($value) ? $value : json_encode($value) }}</strong></div> @endforeach
                </div></div></div>
            @endforeach
        </div>
    @endif
</div>
