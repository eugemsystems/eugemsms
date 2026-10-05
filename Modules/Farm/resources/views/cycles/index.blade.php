<div>
    <h4 class="mb-1">{{ __('Crop cycles') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Crop') }}</th><th>{{ __('Status') }}</th><th>{{ __('Cost') }}</th><th>{{ __('Cost/kg') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($cycles as $cycle)
                                <tr wire:key="cycle-{{ $cycle->id }}">
                                    <td>{{ $cycle->cycle_reference }}</td>
                                    <td>{{ $cycle->crop }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $cycle->status }}</span></td>
                                    <td>{{ number_format($cycle->total_cost_minor / 100, 2) }}</td>
                                    <td>{{ $cycle->cost_per_kg_minor !== null ? number_format($cycle->cost_per_kg_minor / 100, 2) : '—' }}</td>
                                    <td><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="select({{ $cycle->id }})">{{ __('Open') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No crop cycles.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selected)
                <div class="card mt-3">
                    <div class="card-header">{{ $selected->cycle_reference }} — {{ $selected->crop }}</div>
                    <div class="card-body">
                        <h6>{{ __('Record input') }}</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-3"><select class="form-select form-select-sm" wire:model="inputStoreId"><option value="">{{ __('Store') }}</option>@foreach ($stores as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach</select></div>
                            <div class="col-3"><select class="form-select form-select-sm" wire:model="inputItemId"><option value="">{{ __('Item') }}</option>@foreach ($items as $i)<option value="{{ $i->id }}">{{ $i->name }}</option>@endforeach</select></div>
                            <div class="col-2"><select class="form-select form-select-sm" wire:model="inputType">@foreach (['seed','fertiliser','chemical','fuel','water','other'] as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></div>
                            <div class="col-2"><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="inputQuantity" placeholder="{{ __('Qty') }}"></div>
                            <div class="col-2"><button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="recordInput({{ $selected->id }})">{{ __('Record') }}</button></div>
                        </div>
                        <input type="text" class="form-control form-control-sm mb-2" wire:model="inputDescription" placeholder="{{ __('Description') }}">
                        <ul class="list-unstyled small">
                            @foreach ($selected->inputs as $input)
                                <li>{{ $input->description }} — {{ $input->quantity }} {{ $input->unit }} — {{ number_format($input->cost_minor / 100, 2) }}</li>
                            @endforeach
                        </ul>

                        <hr>
                        <h6>{{ __('Allocate labour') }}</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-4"><input type="number" step="0.5" class="form-control form-control-sm" wire:model="labourDays" placeholder="{{ __('Days') }}"></div>
                            <div class="col-4"><button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="allocateLabour({{ $selected->id }})">{{ __('Allocate') }}</button></div>
                        </div>

                        <h6>{{ __('Apportion overhead') }}</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-4"><input type="number" class="form-control form-control-sm" wire:model="overheadPoolMinor" placeholder="{{ __('Pool (minor units)') }}"></div>
                            <div class="col-4"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="overheadTotalHectares" placeholder="{{ __('Total ha in pool') }}"></div>
                            <div class="col-4"><button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="apportionOverhead({{ $selected->id }})">{{ __('Apportion') }}</button></div>
                        </div>

                        @if (! in_array($selected->status, ['failed', 'abandoned', 'harvested']))
                            <hr>
                            <h6>{{ __('Fail cycle') }}</h6>
                            <textarea class="form-control form-control-sm mb-2" wire:model="failureReason" placeholder="{{ __('Failure reason') }}"></textarea>
                            <div class="row g-2 mb-2">
                                <div class="col-5"><select class="form-select form-select-sm" wire:model="cropFailureExpenseAccountId"><option value="">{{ __('Failure expense account') }}</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }}</option>@endforeach</select></div>
                                <div class="col-5"><select class="form-select form-select-sm" wire:model="originalExpenseAccountId"><option value="">{{ __('Original expense account') }}</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }}</option>@endforeach</select></div>
                                <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100" wire:click="fail({{ $selected->id }})">{{ __('Fail') }}</button></div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Plan cycle') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="productionUnitId">
                        <option value="">{{ __('Production unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="fieldId">
                        <option value="">{{ __('Field') }}</option>
                        @foreach ($fields as $field)
                            <option value="{{ $field->id }}">{{ $field->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="cycleReference" placeholder="{{ __('Cycle reference') }}">
                    <input type="text" class="form-control mb-2" wire:model="crop" placeholder="{{ __('Crop') }}">
                    <input type="text" class="form-control mb-2" wire:model="variety" placeholder="{{ __('Variety (optional)') }}">
                    <select class="form-select mb-2" wire:model="season">
                        <option value="summer">{{ __('Summer') }}</option>
                        <option value="winter">{{ __('Winter') }}</option>
                        <option value="irrigated">{{ __('Irrigated') }}</option>
                    </select>
                    <input type="number" step="0.0001" class="form-control mb-2" wire:model="areaPlantedHectares" placeholder="{{ __('Area planted (hectares)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="plan">{{ __('Plan cycle') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
