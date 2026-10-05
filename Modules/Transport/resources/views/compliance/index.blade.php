<div>
    <h4 class="mb-1">{{ __('Vehicle compliance') }} 🇿🇼</h4>
    <p class="text-body-secondary mb-1">{{ __('Every certificate, tracked separately with its own expiry — vehicle licence, ZINARA, certificate of fitness, insurance, passenger insurance, route permit, radio licence.') }}</p>
    <p class="text-body-secondary small mb-4">{{ __('Alert window (configured, not hard-coded):') }} {{ implode(', ', (array) $alertDays) }} {{ __('days before expiry.') }}</p>

    <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="checkExpiry">{{ __('Check expiry now') }}</button>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Vehicle') }}</th><th>{{ __('Type') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr wire:key="comp-{{ $record->id }}" class="{{ $record->status === 'expired' ? 'table-danger' : '' }}">
                                    <td>{{ $record->vehicle->fleet_number }}</td>
                                    <td>{{ $record->compliance_type }}</td>
                                    <td>{{ $record->expires_on->toFormattedDateString() }}</td>
                                    <td>{{ $record->status }}</td>
                                    <td>
                                        @if ($record->status === 'expired' && $record->renewal_wo_id === null)
                                            <select class="form-select form-select-sm d-inline-block mb-1" style="width:8rem" wire:model="renewalCostCentreId.{{ $record->id }}">
                                                <option value="">{{ __('Cost centre') }}</option>
                                                @foreach ($costCentres as $cc)
                                                    <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-warning" wire:click="raiseRenewal({{ $record->id }})">{{ __('Raise renewal WO') }}</button>
                                        @elseif ($record->renewal_wo_id !== null)
                                            <span class="badge text-bg-info">{{ __('renewal raised') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No compliance records.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Register compliance record') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="vehicleId">
                        <option value="">{{ __('Vehicle') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->fleet_number }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="complianceType">
                        @foreach (['vehicle_licence', 'zinara', 'certificate_of_fitness', 'insurance', 'passenger_insurance', 'route_permit', 'radio_licence', 'carbon_tax'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="referenceNumber" placeholder="{{ __('Reference number (optional)') }}">
                    <input type="date" class="form-control mb-2" wire:model="issuedOn" placeholder="{{ __('Issued on') }}">
                    <input type="date" class="form-control mb-2" wire:model="expiresOn" placeholder="{{ __('Expires on') }}">
                    <input type="number" class="form-control mb-2" wire:model="costMinor" placeholder="{{ __('Cost (minor units, optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="issuingAuthority" placeholder="{{ __('Issuing authority (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
