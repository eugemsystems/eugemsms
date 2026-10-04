<div>
    <h4 class="mb-1">{{ __('Behaviour review queue') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Records flagged for head or HOD review. Safeguarding-paused records never appear here.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Student') }}</th><th>{{ __('Category') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="review-{{ $record->id }}">
                            <td>{{ $record->occurred_at->toDateString() }}</td>
                            <td>{{ $record->student?->first_name }} {{ $record->student?->last_name }}</td>
                            <td>{{ $record->category?->name }}</td>
                            <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $record->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nothing awaiting review.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
