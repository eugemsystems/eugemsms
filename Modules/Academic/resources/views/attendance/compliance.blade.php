<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Marking compliance') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Did teachers actually mark their registers today?') }}</p>
        </div>
        <button type="button" class="btn btn-outline-primary" wire:click="recompute">{{ __('Recompute for this date') }}</button>
    </div>

    <input type="date" class="form-control mb-3" style="max-width: 220px" wire:model.live="date">

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Teacher') }}</th><th>{{ __('Expected') }}</th><th>{{ __('Marked') }}</th><th>{{ __('Late') }}</th><th>{{ __('Compliance') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="compliance-{{ $row->id }}" class="{{ $row->compliance_percent !== null && $row->compliance_percent < 100 ? 'table-warning' : '' }}">
                            <td>{{ $row->staff?->first_name }} {{ $row->staff?->last_name }}</td>
                            <td>{{ $row->expected_sessions }}</td>
                            <td>{{ $row->marked_sessions }}</td>
                            <td>{{ $row->marked_late_sessions }}</td>
                            <td>{{ $row->compliance_percent !== null ? number_format((float) $row->compliance_percent, 1).'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No compliance data for this date yet — recompute above.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
