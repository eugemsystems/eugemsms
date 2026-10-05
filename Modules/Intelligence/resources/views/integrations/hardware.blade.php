<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Hardware devices') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Each reader has its own narrow credential. A silent device is flagged offline — manual marking in attendance, roll call and the gate is never affected.') }}</p>
    </div>
    @if ($revealedKey)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1"><strong>{{ __('Copy this now — it is shown only once.') }}</strong> {{ __('Device') }} {{ $revealedFor }}<br><code class="user-select-all">{{ $revealedKey }}</code></div>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="dismissKey">{{ __('I have copied it') }}</button>
        </div>
    @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center"><span>{{ __('Devices') }}</span><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="markSilentOffline">{{ __('Flag silent devices') }}</button></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Location') }}</th><th>{{ __('Last heartbeat') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($devices as $device)
                                <tr wire:key="hd-{{ $device->id }}">
                                    <td>{{ str_replace('_', ' ', $device->device_type) }}</td>
                                    <td>{{ $device->purpose }}</td>
                                    <td>{{ $device->location ?? '—' }}</td>
                                    <td class="small">{{ $device->last_heartbeat_at?->diffForHumans() ?? __('never') }}</td>
                                    <td><span class="badge text-bg-{{ ['online' => 'success', 'offline' => 'secondary', 'fault' => 'danger'][$device->status] ?? 'secondary' }}">{{ __(ucfirst($device->status)) }}</span></td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="rotate({{ $device->id }})" wire:confirm="{{ __('Rotate this device key? The reader must be reconfigured.') }}">{{ __('Rotate key') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No devices registered.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Register a device') }}</div>
                <div class="card-body">
                    <select class="form-select form-select-sm mb-2" wire:model="deviceType">
                        @foreach ($deviceTypes as $type) <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option> @endforeach
                    </select>
                    <select class="form-select form-select-sm mb-2" wire:model="purpose">
                        <option value="">{{ __('Purpose…') }}</option>
                        @foreach ($purposes as $value) <option value="{{ $value }}">{{ $value }}</option> @endforeach
                    </select>
                    @error('purpose') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="location" placeholder="{{ __('Location, e.g. Main gate') }}">
                    @error('location') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="registerDevice">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
