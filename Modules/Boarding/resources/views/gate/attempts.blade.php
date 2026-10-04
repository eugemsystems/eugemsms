<div>
    <h4 class="mb-1">{{ __('Collection attempts') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Append-only — every refusal is logged permanently, because the pattern is the signal.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Learner') }}</th><th>{{ __('Claimed by') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        <tr class="{{ $attempt->outcome !== 'released' ? 'table-danger' : '' }}">
                            <td>{{ $attempt->occurred_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $attempt->student->first_name }} {{ $attempt->student->last_name }}</td>
                            <td>{{ $attempt->attempted_by_name }}</td>
                            <td>{{ $attempt->claimed_relationship }}</td>
                            <td><span class="badge text-bg-{{ $attempt->outcome === 'released' ? 'success' : 'danger' }}">{{ ucfirst($attempt->outcome) }}</span></td>
                            <td>{{ $attempt->refusal_reason ? str_replace('_', ' ', $attempt->refusal_reason) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No attempts recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
