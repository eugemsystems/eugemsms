<div>
    <h4 class="mb-1">{{ __('Trip scheduling') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Distance') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($trips as $trip)
                                <tr wire:key="trip-{{ $trip->id }}">
                                    <td>{{ $trip->trip_date->toFormattedDateString() }}</td>
                                    <td>{{ $trip->trip_type }}</td>
                                    <td>{{ $trip->status }}</td>
                                    <td>{{ $trip->distance_km ?? '—' }}</td>
                                    <td><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="select({{ $trip->id }})">{{ __('Open') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No trips.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selected)
                <div class="card mt-3">
                    <div class="card-header">{{ __('Trip —') }} {{ $selected->trip_date->toFormattedDateString() }}</div>
                    <div class="card-body">
                        @if ($selected->status === 'scheduled')
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="departureOdometer" placeholder="{{ __('Departure odometer (km)') }}"></div>
                                <div class="col-6"><button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="recordDeparture({{ $selected->id }})">{{ __('Record departure') }}</button></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" wire:click="depart({{ $selected->id }})">{{ __('Mark departed') }}</button>
                        @endif

                        @if ($selected->status === 'departed' || $selected->departure_odometer !== null)
                            <div class="row g-2 mt-2">
                                <div class="col-6"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="returnOdometer" placeholder="{{ __('Return odometer (km)') }}"></div>
                                <div class="col-6"><button type="button" class="btn btn-sm btn-outline-success w-100" wire:click="recordReturn({{ $selected->id }})">{{ __('Record return') }}</button></div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Schedule a trip') }}</div>
                <div class="card-body">
                    <input type="date" class="form-control mb-2" wire:model="tripDate">
                    <select class="form-select mb-2" wire:model="tripType">
                        @foreach (['route', 'fixture', 'excursion', 'medical', 'staff', 'procurement'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="routeId">
                        <option value="">{{ __('Route (optional)') }}</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="vehicleId">
                        <option value="">{{ __('Vehicle') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->fleet_number }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="driverId">
                        <option value="">{{ __('Driver') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->staff->fullName() }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="purpose" placeholder="{{ __('Purpose (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="destination" placeholder="{{ __('Destination (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule trip') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
