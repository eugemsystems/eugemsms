<div>
    <h4 class="mb-1">{{ __('Fuel log') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Vehicle') }}</th><th>{{ __('Date') }}</th><th>{{ __('Litres') }}</th><th>{{ __('km/l') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr wire:key="fuel-{{ $log->id }}" class="{{ $log->is_anomaly ? 'table-warning' : '' }}">
                                    <td>{{ $log->vehicle->fleet_number }}</td>
                                    <td>{{ $log->fuelled_at->toFormattedDateString() }}</td>
                                    <td>{{ $log->litres }}</td>
                                    <td>{{ $log->km_per_litre ?? '—' }}</td>
                                    <td>@if ($log->is_anomaly) <span class="badge text-bg-warning">⭐ {{ __('anomaly') }}</span> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No fuel logs.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Log fuel') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="vehicleId">
                        <option value="">{{ __('Vehicle') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->fleet_number }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" class="form-control mb-2" wire:model="fuelledAt">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="odometerKm" placeholder="{{ __('Odometer (km)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="litres" placeholder="{{ __('Litres') }}">
                    <input type="number" class="form-control mb-2" wire:model="unitPriceMinor" placeholder="{{ __('Unit price (minor units)') }}">
                    <select class="form-select mb-2" wire:model.live="source">
                        <option value="filling_station">{{ __('Filling station') }}</option>
                        <option value="school_tank">{{ __('School tank') }}</option>
                        <option value="coupon">{{ __('Coupon') }}</option>
                    </select>
                    @if ($source === 'school_tank')
                        <select class="form-select mb-2" wire:model="storeId">
                            <option value="">{{ __('Store') }}</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->code }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="itemId">
                            <option value="">{{ __('Fuel item') }}</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
