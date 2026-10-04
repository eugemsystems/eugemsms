<div>
    <h4 class="mb-1">{{ __('Roll call board') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Conducted, pending and missing counts, by hostel. Doubles as roll call history — pick any date.') }}</p>

    <input type="date" class="form-control mb-4" style="max-width: 220px" wire:model.live="date">

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Hostel') }}</th><th>{{ __('Point') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expected') }}</th><th>{{ __('Present') }}</th><th>{{ __('Accounted') }}</th><th>{{ __('Missing') }}</th></tr></thead>
                <tbody>
                    @forelse ($rollCalls as $rollCall)
                        <tr wire:key="rc-{{ $rollCall->id }}">
                            <td>{{ $rollCall->hostel->code }}</td>
                            <td>{{ $rollCall->point->name }}</td>
                            <td><span class="badge text-bg-{{ $rollCall->status === 'completed' ? 'success' : ($rollCall->status === 'missed' ? 'danger' : 'secondary') }}">{{ str_replace('_', ' ', $rollCall->status) }}</span></td>
                            <td>{{ $rollCall->expected_count }}</td>
                            <td>{{ $rollCall->present_count }}</td>
                            <td>{{ $rollCall->accounted_count }}</td>
                            <td>
                                @if ($rollCall->missing_count > 0)
                                    <span class="badge text-bg-danger">{{ $rollCall->missing_count }}</span>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No roll calls on this date.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
