<div>
    <h4 class="mb-1">{{ __('Routes & zones') }}</h4>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Transport zones') }} ⭐</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Termly fee') }}</th></tr></thead>
                        <tbody>
                            @forelse ($zones as $zone)
                                <tr wire:key="zone-{{ $zone->id }}">
                                    <td>{{ $zone->code }}</td>
                                    <td>{{ $zone->name }}</td>
                                    <td>{{ number_format($zone->termly_fee_minor / 100, 2) }} {{ $zone->currency }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No zones.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('New zone') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-3"><input type="text" class="form-control form-control-sm" wire:model="zoneCode" placeholder="{{ __('Code') }}"></div>
                        <div class="col-4"><input type="text" class="form-control form-control-sm" wire:model="zoneName" placeholder="{{ __('Name') }}"></div>
                        <div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="zoneTermlyFeeMinor" placeholder="{{ __('Termly fee') }}"></div>
                        <div class="col-2"><button type="button" class="btn btn-sm btn-primary w-100" wire:click="createZone">{{ __('Add') }}</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('Routes') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Direction') }}</th><th>{{ __('Capacity') }}</th><th>{{ __('Stops') }}</th></tr></thead>
                        <tbody>
                            @forelse ($routes as $route)
                                <tr wire:key="route-{{ $route->id }}">
                                    <td>{{ $route->code }}</td>
                                    <td>{{ $route->name }}</td>
                                    <td>{{ $route->direction }}</td>
                                    <td>{{ $route->current_passengers }}/{{ $route->capacity }}</td>
                                    <td>{{ $route->stops->count() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No routes.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New route') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="routeCode" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="routeName" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="direction">
                        <option value="morning">{{ __('Morning') }}</option>
                        <option value="afternoon">{{ __('Afternoon') }}</option>
                        <option value="both">{{ __('Both') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="capacity" placeholder="{{ __('Capacity') }}">
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="assignedVehicleId">
                        <option value="">{{ __('Vehicle (optional)') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->fleet_number }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="assignedDriverId">
                        <option value="">{{ __('Driver (optional)') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->staff->fullName() }}</option>
                        @endforeach
                    </select>

                    <h6 class="mt-3">{{ __('Stops') }}</h6>
                    @foreach ($stops as $i => $stop)
                        <div class="row g-1 mb-1" wire:key="stop-{{ $i }}">
                            <div class="col-4"><input type="text" class="form-control form-control-sm" wire:model="stops.{{ $i }}.name" placeholder="{{ __('Stop name') }}"></div>
                            <div class="col-3"><input type="text" class="form-control form-control-sm" wire:model="stops.{{ $i }}.landmark" placeholder="{{ __('Landmark') }}"></div>
                            <div class="col-2"><select class="form-select form-select-sm" wire:model="stops.{{ $i }}.zoneId"><option value="">{{ __('Zone') }}</option>@foreach ($zones as $zone)<option value="{{ $zone->id }}">{{ $zone->code }}</option>@endforeach</select></div>
                            <div class="col-2"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="stops.{{ $i }}.distanceFromSchoolKm" placeholder="{{ __('km') }}"></div>
                            <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeStop({{ $i }})">&times;</button></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" wire:click="addStop">{{ __('+ Add stop') }}</button>
                    <br>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createRoute">{{ __('Create route') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
