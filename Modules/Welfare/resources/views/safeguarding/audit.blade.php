<div>
    <h4 class="mb-1">{{ __('Safeguarding audit') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Hash-chained, append-only, separate from the general audit log. Break-glass use is highlighted.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Seq') }}</th><th>{{ __('Event') }}</th><th>{{ __('User') }}</th><th>{{ __('Basis') }}</th><th>{{ __('IP') }}</th><th>{{ __('When') }}</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr wire:key="audit-{{ $entry->id }}" class="{{ $entry->event_type === 'break_glass' ? 'table-danger' : ($entry->event_type === 'vendor_blocked' ? 'table-warning' : '') }}">
                            <td>{{ $entry->sequence }}</td>
                            <td>{{ str_replace('_', ' ', $entry->event_type) }}</td>
                            <td>{{ $entry->user_id }} ({{ $entry->user_role_at_time }})</td>
                            <td>{{ $entry->access_basis }}</td>
                            <td>{{ $entry->ip_address }}</td>
                            <td>{{ $entry->occurred_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No audit entries yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
