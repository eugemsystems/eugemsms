<div>
    <h4 class="mb-1">{{ __('Fleet register') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Fleet #') }}</th><th>{{ __('Registration') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Odometer') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($vehicles as $vehicle)
                                <tr wire:key="veh-{{ $vehicle->id }}" class="{{ $vehicle->status === 'grounded' ? 'table-danger' : '' }}">
                                    <td>{{ $vehicle->fleet_number }}</td>
                                    <td>{{ $vehicle->registration_number }}</td>
                                    <td>{{ $vehicle->vehicle_type }}</td>
                                    <td>{{ $vehicle->status }}</td>
                                    <td>{{ number_format((float) $vehicle->current_odometer_km, 0) }} km</td>
                                    <td>
                                        @if ($vehicle->status !== 'grounded')
                                            <input type="text" class="form-control form-control-sm d-inline-block mb-1" style="width:10rem" wire:model="groundReason.{{ $vehicle->id }}" placeholder="{{ __('Reason') }}">
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="ground({{ $vehicle->id }})">{{ __('Ground') }}</button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="reactivate({{ $vehicle->id }})">{{ __('Reactivate') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No vehicles.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New vehicle') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="fleetNumber" placeholder="{{ __('Fleet number') }}">
                    <input type="text" class="form-control mb-2" wire:model="registrationNumber" placeholder="{{ __('Registration number') }}">
                    <select class="form-select mb-2" wire:model="vehicleType">
                        @foreach (['bus', 'minibus', 'coaster', 'truck', 'pickup', 'tractor', 'car', 'ambulance'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="number" class="form-control" wire:model="seatingCapacity" placeholder="{{ __('Seating capacity') }}"></div>
                        <div class="col-6"><input type="number" class="form-control" wire:model="standingCapacity" placeholder="{{ __('Standing capacity') }}"></div>
                    </div>
                    <select class="form-select mb-2" wire:model="fuelType">
                        <option value="diesel">{{ __('Diesel') }}</option>
                        <option value="petrol">{{ __('Petrol') }}</option>
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="number" step="0.01" class="form-control" wire:model="tankCapacityLitres" placeholder="{{ __('Tank (litres)') }}"></div>
                        <div class="col-6"><input type="number" step="0.01" class="form-control" wire:model="expectedKmPerLitre" placeholder="{{ __('Expected km/l') }} ⭐"></div>
                    </div>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Register vehicle') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
