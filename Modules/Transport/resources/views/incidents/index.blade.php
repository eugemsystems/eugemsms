<div>
    <h4 class="mb-1">{{ __('Vehicle incidents') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Vehicle') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('Injuries') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($incidents as $incident)
                                <tr wire:key="incident-{{ $incident->id }}" class="{{ $incident->injuries ? 'table-danger' : '' }}">
                                    <td>{{ $incident->vehicle->fleet_number }}</td>
                                    <td>{{ $incident->incident_type }}</td>
                                    <td>{{ $incident->occurred_at->toFormattedDateString() }}</td>
                                    <td>{{ $incident->injuries ? __('Yes') : __('No') }}</td>
                                    <td>{{ $incident->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No incidents.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Report incident') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="vehicleId">
                        <option value="">{{ __('Vehicle') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->fleet_number }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="incidentType">
                        @foreach (['accident', 'breakdown', 'traffic_offence', 'theft', 'passenger_injury', 'near_miss'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" class="form-control mb-2" wire:model="occurredAt">
                    <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location') }}">
                    <textarea class="form-control mb-2" wire:model="description" rows="2" placeholder="{{ __('Description') }}"></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="injuries" wire:model="injuries">
                        <label class="form-check-label" for="injuries">{{ __('Injuries involved') }}</label>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="estimatedDamageMinor" placeholder="{{ __('Estimated damage (minor units, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="report">{{ __('Report incident') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
