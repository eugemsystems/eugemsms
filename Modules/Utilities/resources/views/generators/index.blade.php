<div>
    <h4 class="mb-1">{{ __('Generators') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Capacity (kVA)') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($generators as $gen)
                                <tr wire:key="generator-{{ $gen->id }}">
                                    <td>{{ $gen->code }}</td>
                                    <td>{{ $gen->name }}</td>
                                    <td>{{ number_format((float) $gen->capacity_kva, 2) }}</td>
                                    <td>{{ number_format((float) $gen->current_hours, 1) }}</td>
                                    <td>{{ $gen->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No generators.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New generator') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="capacityKva" placeholder="{{ __('Capacity (kVA)') }}">
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="fuelType" placeholder="{{ __('Fuel type') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="tankCapacityLitres" placeholder="{{ __('Tank capacity (litres, optional)') }}">
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="expectedLitresPerHour" placeholder="{{ __('Expected litres/hour (anomaly baseline, optional)') }}">
                    <select class="form-select mb-2" wire:model="servesScope">
                        @foreach (['whole_school', 'building', 'hostel', 'department', 'farm', 'staff_housing'] as $scope)
                            <option value="{{ $scope }}">{{ $scope }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Register generator') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
