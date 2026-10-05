<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Report builder') }} ⭐</h4>
            <p class="text-body-secondary small mb-0">{{ __('Build the report nobody thought to pre-build. You can only choose fields you are already allowed to read, and the same check runs again on the server every time.') }}</p>
        </div>
        <a href="{{ route('insights.reports.index', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('My reports') }}</a>
    </div>

    @error('entity') <div class="alert alert-danger small">{{ $message }}</div> @enderror

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('1. What to report on') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="entity">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($entities as $key) <option value="{{ $key }}">{{ str_replace('_', ' ', $key) }}</option> @endforeach
                    </select>
                    @if ($entity !== '')
                        <div class="fw-semibold small mb-1">{{ __('Fields') }}</div>
                        @error('selected') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                        @forelse ($fields as $field)
                            <div class="form-check" wire:key="f-{{ $field->fieldKey }}">
                                <input class="form-check-input" type="checkbox" id="f-{{ $field->fieldKey }}" value="{{ $field->fieldKey }}" wire:model.live="selected">
                                <label class="form-check-label small" for="f-{{ $field->fieldKey }}">{{ $field->label }} @if ($field->isSensitive) <span class="badge bg-label-danger">{{ __('sensitive') }}</span> @endif</label>
                            </div>
                        @empty
                            <div class="text-body-secondary small">{{ __('You may not read any field of this entity.') }}</div>
                        @endforelse
                    @endif
                </div>
            </div>

            @if ($canConsolidate)
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="rb-group" wire:model="consolidate"><label class="form-check-label small" for="rb-group">{{ __('Consolidate across all my schools in this group') }}</label></div>
            @endif
        </div>

        <div class="col-lg-7">
            @if ($entity !== '')
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between"><span>{{ __('2. Filters') }}</span><button type="button" class="btn btn-xs btn-outline-secondary" wire:click="addFilter">{{ __('Add') }}</button></div>
                    <div class="card-body">
                        @forelse ($filters as $index => $filter)
                            <div class="row g-2 mb-2" wire:key="flt-{{ $index }}">
                                <div class="col-4">
                                    <select class="form-select form-select-sm" wire:model="filters.{{ $index }}.field">
                                        <option value="">{{ __('Field…') }}</option>
                                        @foreach ($filterable as $field) <option value="{{ $field->fieldKey }}">{{ $field->label }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-2"><select class="form-select form-select-sm" wire:model="filters.{{ $index }}.operator">@foreach ($operators as $operator) <option value="{{ $operator }}">{{ $operator }}</option> @endforeach</select></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm" wire:model="filters.{{ $index }}.value" placeholder="{{ __('Value') }}"></div>
                                <div class="col-2"><input type="number" class="form-control form-control-sm" min="1" wire:model="filters.{{ $index }}.group" title="{{ __('Group — AND within, OR across') }}"></div>
                                <div class="col-1"><button type="button" class="btn btn-sm btn-icon btn-outline-danger" wire:click="removeFilter({{ $index }})"><i class="ri ri-close-line"></i></button></div>
                            </div>
                        @empty
                            <div class="text-body-secondary small">{{ __('No filters — every row.') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between"><span>{{ __('3. Group & total') }}</span><button type="button" class="btn btn-xs btn-outline-secondary" wire:click="addAggregation">{{ __('Add total') }}</button></div>
                    <div class="card-body">
                        <label class="form-label small mb-0">{{ __('Group by') }}</label>
                        <select class="form-select form-select-sm mb-2" multiple wire:model="groupBy">
                            @foreach ($groupable as $field) <option value="{{ $field->fieldKey }}">{{ $field->label }}</option> @endforeach
                        </select>
                        @foreach ($aggregations as $index => $aggregation)
                            <div class="row g-2 mb-1" wire:key="agg-{{ $index }}">
                                <div class="col-5"><select class="form-select form-select-sm" wire:model="aggregations.{{ $index }}.function">@foreach ($aggregates as $function) <option value="{{ $function }}">{{ $function }}</option> @endforeach</select></div>
                                <div class="col-6"><select class="form-select form-select-sm" wire:model="aggregations.{{ $index }}.field"><option value="">{{ __('of field…') }}</option>@foreach ($aggregatable as $field) <option value="{{ $field->fieldKey }}">{{ $field->label }}</option> @endforeach</select></div>
                                <div class="col-1"><button type="button" class="btn btn-sm btn-icon btn-outline-danger" wire:click="removeAggregation({{ $index }})"><i class="ri ri-close-line"></i></button></div>
                            </div>
                        @endforeach
                        <div class="small text-body-secondary mt-1">{{ __('Totals are offered for selected fields that can be totalled.') }}</div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary btn-sm" wire:click="run">{{ __('Run') }}</button>

                <div class="card mt-3">
                    <div class="card-header">{{ __('4. Save') }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Report name') }}">
                        @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input type="text" class="form-control mb-2" wire:model="description" placeholder="{{ __('Description (optional)') }}">
                        <select class="form-select mb-2" wire:model="chartType">
                            <option value="">{{ __('Chart type (stored for later)…') }}</option>
                            <option value="table">{{ __('Table') }}</option><option value="bar">{{ __('Bar') }}</option><option value="line">{{ __('Line') }}</option><option value="pie">{{ __('Pie') }}</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="save">{{ __('Save report') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('intelligence::insights.reports.partials.result', ['result' => $result])
</div>
