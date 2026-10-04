<div>
    <h4 class="mb-1">{{ __('Visitor log') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Nobody stays on a boarding campus overnight unaccounted for.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Visitor') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Signed in') }}</th><th>{{ __('Signed out') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="{{ $entry->signed_out_at === null && $entry->signed_in_at->lt(now()->subHours(12)) ? 'table-warning' : '' }}">
                            <td>{{ $entry->visitor->full_name }}</td>
                            <td>{{ str_replace('_', ' ', $entry->visit_purpose) }}</td>
                            <td>{{ $entry->signed_in_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $entry->signed_out_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-end">
                                @if ($entry->signed_out_at === null)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="checkNow({{ $entry->id }})">{{ __('Check overdue') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No visitors logged yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
