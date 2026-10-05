<div>
    <h4 class="mb-1">{{ __('Production units') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Each unit is its own cost centre — its own profit and loss.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Hectares') }}</th></tr></thead>
                        <tbody>
                            @forelse ($units as $unit)
                                <tr wire:key="unit-{{ $unit->id }}">
                                    <td>{{ $unit->code }}</td>
                                    <td>{{ $unit->name }}</td>
                                    <td>{{ $unit->unit_type }}</td>
                                    <td>{{ $unit->area_hectares ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No production units.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New production unit') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="unitType">
                        @foreach (['crop', 'livestock', 'poultry', 'dairy', 'orchard', 'vegetable_garden', 'piggery', 'fishery'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="managerStaffId">
                        <option value="">{{ __('Manager (optional)') }}</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="storeId">
                        <option value="">{{ __('Farm store (optional)') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.0001" class="form-control mb-2" wire:model="areaHectares" placeholder="{{ __('Area (hectares, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create unit') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
