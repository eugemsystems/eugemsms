<div>
    <h4 class="mb-1">{{ __('Fields') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Hectares') }}</th><th>{{ __('Irrigated') }}</th></tr></thead>
                        <tbody>
                            @forelse ($fields as $field)
                                <tr wire:key="field-{{ $field->id }}">
                                    <td>{{ $field->code }}</td>
                                    <td>{{ $field->name }}</td>
                                    <td>{{ $field->productionUnit->name }}</td>
                                    <td>{{ $field->area_hectares }}</td>
                                    <td>{{ $field->is_irrigated ? $field->irrigation_type : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No fields.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New field') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="productionUnitId">
                        <option value="">{{ __('Production unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <input type="number" step="0.0001" class="form-control mb-2" wire:model="areaHectares" placeholder="{{ __('Area (hectares)') }}">
                    <input type="text" class="form-control mb-2" wire:model="soilType" placeholder="{{ __('Soil type (optional)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isIrrigated" wire:model.live="isIrrigated">
                        <label class="form-check-label" for="isIrrigated">{{ __('Irrigated') }}</label>
                    </div>
                    @if ($isIrrigated)
                        <select class="form-select mb-2" wire:model="irrigationType">
                            <option value="drip">{{ __('Drip') }}</option>
                            <option value="sprinkler">{{ __('Sprinkler') }}</option>
                            <option value="flood">{{ __('Flood') }}</option>
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create field') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
