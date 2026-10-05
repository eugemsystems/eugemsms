<div>
    <h4 class="mb-1">{{ __('Solar') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">{{ __('Installations') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('kWp') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($installations as $inst)
                                <tr wire:key="inst-{{ $inst->id }}">
                                    <td>{{ $inst->code }}</td>
                                    <td>{{ $inst->name }}</td>
                                    <td>{{ number_format((float) $inst->capacity_kwp, 2) }}</td>
                                    <td>{{ $inst->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No installations.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Generation log') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Installation') }}</th><th>{{ __('Generated') }}</th><th>{{ __('Grid offset') }}</th></tr></thead>
                        <tbody>
                            @forelse ($generation as $gen)
                                <tr wire:key="gen-{{ $gen->id }}">
                                    <td>{{ $gen->record_date->toDateString() }}</td>
                                    <td>{{ $gen->installation->code }}</td>
                                    <td>{{ number_format((float) $gen->kwh_generated, 2) }} kWh</td>
                                    <td>{{ $gen->grid_offset_kwh !== null ? number_format((float) $gen->grid_offset_kwh, 2).' kWh' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No generation recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New installation') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="capacityKwp" placeholder="{{ __('Capacity (kWp)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="batteryCapacityKwh" placeholder="{{ __('Battery capacity (kWh, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createInstallation">{{ __('Register installation') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Record generation') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="installationId">
                        <option value="">{{ __('Installation') }}</option>
                        @foreach ($installations as $inst)
                            <option value="{{ $inst->id }}">{{ $inst->code }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="recordDate">
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="kwhGenerated" placeholder="{{ __('kWh generated') }}">
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="kwhConsumed" placeholder="{{ __('kWh consumed (optional)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="batteryStatePercent" placeholder="{{ __('Battery state % (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="recordGeneration">{{ __('Record generation') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
