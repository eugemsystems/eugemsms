<div>
    <h4 class="mb-1">{{ __('Chronic absentees') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Attendance below the chronic threshold this term, from the last rebuilt summary.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Attendance %') }}</th><th>{{ __('Longest unexplained run') }}</th><th>{{ __('Last rebuilt') }}</th></tr></thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        <tr wire:key="summary-{{ $summary->id }}">
                            <td>{{ $summary->student?->first_name }} {{ $summary->student?->last_name }}</td>
                            <td>{{ $summary->attendance_percent !== null ? number_format((float) $summary->attendance_percent, 1).'%' : '—' }}</td>
                            <td>{{ $summary->consecutive_absent_max }} {{ __('days') }}</td>
                            <td>{{ $summary->rebuilt_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No chronic absentees recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
