<div>
    <h4 class="mb-1">{{ __('Fiscal day control') }}</h4>
    <p class="text-body-secondary small">{{ __('Opening never waits on FDMS. A day is closed locally only once FDMS confirms — a close_pending day retries by closing again.') }}</p>

    <div class="card mb-3" style="max-width:30rem">
        <div class="card-body d-flex gap-2">
            <select class="form-select" wire:model="deviceId">
                <option value="">{{ __('Device') }}</option>
                @foreach ($devices as $device)
                    <option value="{{ $device->id }}">{{ $device->device_id }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-primary btn-sm text-nowrap" wire:click="openDay">{{ __('Open day') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Day #') }}</th><th>{{ __('Local status') }}</th><th>{{ __('FDMS status') }}</th><th>{{ __('Receipts') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr wire:key="day-{{ $day->id }}">
                            <td>{{ $day->fiscal_day_number }}</td>
                            <td><span class="badge {{ $day->local_status === 'closed' ? 'bg-success' : ($day->local_status === 'close_failed' ? 'bg-danger' : 'bg-secondary') }}">{{ $day->local_status }}</span></td>
                            <td>{{ $day->fdms_status ?? '—' }}</td>
                            <td>{{ $day->receipt_count }}</td>
                            <td>
                                @if (in_array($day->local_status, ['open', 'close_failed']))
                                    <button type="button" class="btn btn-outline-warning btn-sm" wire:click="closeDay({{ $day->id }})">{{ __('Close day') }}</button>
                                @elseif ($day->local_status === 'closed')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="compileZReport({{ $day->id }})">{{ __('Compile Z-report') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No fiscal days opened yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
