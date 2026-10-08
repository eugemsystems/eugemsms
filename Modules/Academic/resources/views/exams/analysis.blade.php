<div>
    <h4 class="mb-1">{{ __('Examination analysis') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Grade distribution, subject comparison, and year-on-year trend — computed from settled marks.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-5">
            <select class="form-select" wire:model.live="sessionId">
                <option value="">{{ __('Select session') }}</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($report)
        <div class="card mb-3">
            <div class="card-header">{{ __('Subject comparison') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Average %') }}</th><th>{{ __('Median %') }}</th><th>{{ __('Pass rate %') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['subject_comparison'] as $row)
                            <tr wire:key="compare-{{ $row['subject_id'] }}">
                                <td>{{ $row['subject_name'] }}</td>
                                <td>{{ $row['average_percent'] }}</td>
                                <td>{{ $row['median_percent'] }}</td>
                                <td>{{ $row['pass_rate_percent'] ?? __('n/a') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No settled marks for this session yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ __('Grade distribution') }}</div>
            <div class="card-body">
                @forelse ($report['distributions'] as $distribution)
                    <div class="mb-3">
                        <strong>{{ $distribution['subject_name'] }}</strong> <span class="text-body-secondary small">({{ $distribution['candidate_count'] }} {{ __('candidates') }})</span>
                        <div>
                            @foreach ($distribution['bands'] as $grade => $count)
                                <span class="badge text-bg-light border me-1">{{ $grade }}: {{ $count }}</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">{{ __('No settled marks for this session yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header">{{ __('Year-on-year') }}</div>
            <div class="card-body">
                @forelse ($report['year_on_year'] as $trend)
                    <div class="mb-2">
                        <strong>{{ $trend['subject_name'] }}</strong>
                        @foreach ($trend['years'] as $year)
                            <span class="badge text-bg-light border me-1">{{ $year['academic_year_id'] }}: {{ $year['average_percent'] }}%</span>
                        @endforeach
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">{{ __('No prior sessions of this exam type to compare against.') }}</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
