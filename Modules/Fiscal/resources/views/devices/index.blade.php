<div>
    <h4 class="mb-1">{{ __('Fiscal devices') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Sandbox and production devices are strictly separated. Environment is fixed at registration and never changes.') }}</p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="checkExpiry">{{ __('Check certificate expiry') }}</button>
    @if ($checked)
        <div class="alert {{ $flaggedCount > 0 ? 'alert-warning' : 'alert-success' }}">{{ __('Devices flagged this run:') }} {{ $flaggedCount }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Device') }}</th><th>{{ __('Environment') }}</th><th>{{ __('Status') }}</th><th>{{ __('Certificate expires') }}</th></tr></thead>
                        <tbody>
                            @forelse ($devices as $device)
                                <tr wire:key="device-{{ $device->id }}">
                                    <td>{{ $device->device_id }}</td>
                                    <td><span class="badge {{ $device->environment === 'production' ? 'bg-danger' : 'bg-secondary' }}">{{ $device->environment }}</span></td>
                                    <td><span class="badge bg-light text-dark border">{{ $device->status }}</span></td>
                                    <td>{{ $device->certificate_expires_at?->toDateString() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No fiscal devices registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Register device') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="deviceId" placeholder="{{ __('Device ID (ZIMRA-assigned)') }}">
                    <input type="text" class="form-control mb-2" wire:model="deviceSerial" placeholder="{{ __('Device serial') }}">
                    <input type="text" class="form-control mb-2" wire:model="taxpayerName" placeholder="{{ __('Taxpayer name') }}">
                    <input type="text" class="form-control mb-2" wire:model="taxpayerTin" placeholder="{{ __('Taxpayer TIN') }}">
                    <input type="text" class="form-control mb-2" wire:model="vatNumber" placeholder="{{ __('VAT number (optional)') }}">
                    <select class="form-select mb-2" wire:model="environment">
                        <option value="sandbox">{{ __('Sandbox') }}</option>
                        <option value="production">{{ __('Production') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="apiBaseUrl" placeholder="{{ __('API base URL') }}">
                    <input type="text" class="form-control mb-2" wire:model="deviceBranchId" placeholder="{{ __('Branch id (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
