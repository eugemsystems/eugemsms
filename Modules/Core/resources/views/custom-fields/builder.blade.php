<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('New custom field') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('For :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('custom-fields.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to custom fields') }}
        </a>
    </div>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form wire:submit="create">
                <div class="form-floating form-floating-outline mb-3">
                    <input type="text" class="form-control @error('entity_type') is-invalid @enderror" id="entity-type" wire:model="entityType" placeholder=" ">
                    <label for="entity-type">{{ __('Entity type (e.g. student, staff, guardian)') }}</label>
                    @error('entity_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating form-floating-outline mb-3">
                    <input type="text" class="form-control @error('key') is-invalid @enderror" id="field-key" wire:model="key" placeholder=" ">
                    <label for="field-key">{{ __('Key (lowercase, e.g. blood_type) — permanent once created') }}</label>
                    @error('key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating form-floating-outline mb-3">
                    <input type="text" class="form-control @error('label') is-invalid @enderror" id="field-label" wire:model="label" placeholder=" ">
                    <label for="field-label">{{ __('Label') }}</label>
                    @error('label')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating form-floating-outline mb-3">
                    <textarea class="form-control" id="field-description" wire:model="description" style="height: 5rem;" placeholder=" "></textarea>
                    <label for="field-description">{{ __('Description (optional)') }}</label>
                </div>

                <div class="form-floating form-floating-outline mb-3">
                    <select class="form-select @error('data_type') is-invalid @enderror" id="field-data-type" wire:model.live="dataType">
                        @foreach ($dataTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <label for="field-data-type">{{ __('Data type') }}</label>
                    @error('data_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @if (in_array($dataType, ['select', 'multiselect']))
                    <div class="form-floating form-floating-outline mb-3">
                        <input type="text" class="form-control" id="field-options" wire:model="optionsText" placeholder=" ">
                        <label for="field-options">{{ __('Options, comma-separated') }}</label>
                    </div>
                @endif

                <div class="form-floating form-floating-outline mb-3">
                    <input type="text" class="form-control" id="field-validation" wire:model="validationRules" placeholder=" ">
                    <label for="field-validation">{{ __('Validation rules (optional, Laravel rule string)') }}</label>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" id="field-group" wire:model="groupLabel" placeholder=" ">
                            <label for="field-group">{{ __('Group label (optional)') }}</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-floating form-floating-outline">
                            <input type="number" class="form-control" id="field-sort" wire:model="sortOrder" placeholder=" ">
                            <label for="field-sort">{{ __('Sort order') }}</label>
                        </div>
                    </div>
                </div>

                <div class="row row-cols-2 g-2 mb-4">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="field-required" wire:model="isRequired">
                            <label class="form-check-label" for="field-required">{{ __('Required') }}</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="field-searchable" wire:model="isSearchable">
                            <label class="form-check-label" for="field-searchable">{{ __('Searchable') }}</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="field-api" wire:model="isExposedInApi">
                            <label class="form-check-label" for="field-api">{{ __('Exposed in API') }}</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="field-printable" wire:model="isPrintable">
                            <label class="form-check-label" for="field-printable">{{ __('Printable') }}</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create field') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
