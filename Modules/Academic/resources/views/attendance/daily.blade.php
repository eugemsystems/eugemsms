<div>
    <h4 class="mb-1">{{ __('Daily attendance overview') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Every register today: marked, partial, or missing.') }}</p>

    <input type="date" class="form-control mb-3" style="max-width: 220px" wire:model.live="date">

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Mode') }}</th><th>{{ __('Expected') }}</th><th>{{ __('Present') }}</th><th>{{ __('Absent') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td>{{ $session->schoolClass?->name ?? '—' }}</td>
                            <td>{{ ucfirst($session->mode) }}</td>
                            <td>{{ $session->expected_count }}</td>
                            <td>{{ $session->present_count }}</td>
                            <td>{{ $session->absent_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $session->status === 'completed' ? 'success' : ($session->status === 'partial' ? 'warning' : 'danger') }}">{{ ucfirst($session->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No sessions for this date yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
