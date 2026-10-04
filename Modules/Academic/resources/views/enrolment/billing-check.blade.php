<div>
    <h4 class="mb-1">{{ __('Billing reconciliation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Does every part-time learner\'s billed subject count equal their actual subject count?') }}</p>

    @if ($mismatchCount > 0)
        <div class="alert alert-danger">{{ __(':count part-time learner(s) have a mismatch between enrolled and billed subject counts.', ['count' => $mismatchCount]) }}</div>
    @else
        <div class="alert alert-success">{{ __('No mismatches — every part-time learner\'s billed count matches their enrolled count.') }}</div>
    @endif

    <div class="card">
        <div class="card-header">{{ __('Part-time learners this term') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Enrolled count') }}</th><th>{{ __('Billed count') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ $row['mismatch'] ? 'table-danger' : '' }}">
                            <td>{{ $row['student']->first_name }} {{ $row['student']->last_name }}</td>
                            <td>{{ $row['actualCount'] }}</td>
                            <td>{{ $row['billedCount'] }}</td>
                            <td>
                                @if ($row['mismatch'])
                                    <span class="badge text-bg-danger">{{ __('Mismatch') }}</span>
                                @else
                                    <span class="badge text-bg-success">{{ __('OK') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No part-time learners found for the current term.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
