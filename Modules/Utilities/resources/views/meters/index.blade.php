<div>
    <h4 class="mb-1">{{ __('Meters') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Meter #') }}</th><th>{{ __('Type') }}</th><th>{{ __('Location') }}</th><th>{{ __('Balance') }}</th></tr></thead>
                        <tbody>
                            @forelse ($meters as $meter)
                                <tr wire:key="meter-{{ $meter->id }}">
                                    <td>{{ $meter->meter_number }}</td>
                                    <td>{{ $meter->meter_type }}</td>
                                    <td>{{ $meter->location }}</td>
                                    <td>{{ $meter->current_balance_units !== null ? number_format((float) $meter->current_balance_units, 1).' '.$meter->unit : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No meters.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New meter') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="utilityAccountId">
                        <option value="">{{ __('Utility account') }}</option>
                        @foreach ($utilityAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->provider }} — {{ $account->account_number }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="meterNumber" placeholder="{{ __('Meter number') }}">
                    <select class="form-select mb-2" wire:model="meterType">
                        @foreach (['electricity_prepaid', 'electricity_postpaid', 'water', 'submeter'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location') }}">
                    <select class="form-select mb-2" wire:model="servesScope">
                        @foreach (['whole_school', 'building', 'hostel', 'department', 'farm', 'staff_housing'] as $scope)
                            <option value="{{ $scope }}">{{ $scope }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre (optional)') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="unit" placeholder="{{ __('Unit (kWh, m3)') }}">
                    <input type="number" step="0.0001" class="form-control mb-2" wire:model="multiplier" placeholder="{{ __('Multiplier') }}">
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="lowBalanceThreshold" placeholder="{{ __('Low balance threshold (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Register meter') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
