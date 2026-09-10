<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'clone'])

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <h5 class="mb-1">{{ __('Copy structure from another school') }}</h5>
            <p class="text-body-secondary">
                {{ __('Copies sections, grade levels, and houses into :name. Enrolment, staff, and academic-year data are never touched.', ['name' => $school->name]) }}
            </p>

            <form wire:submit="clone">
                <div class="form-floating form-floating-outline mb-3">
                    <select class="form-select @error('sourceSchoolId') is-invalid @enderror" id="sourceSchoolId" wire:model="sourceSchoolId">
                        <option value="">{{ __('Select a school...') }}</option>
                        @foreach ($candidateSchools as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->code }})</option>
                        @endforeach
                    </select>
                    <label for="sourceSchoolId">{{ __('Copy from') }}</label>
                    @error('sourceSchoolId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="cloneSections" wire:model="cloneSections">
                    <label class="form-check-label" for="cloneSections">{{ __('Sections') }}</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="cloneGradeLevels" wire:model="cloneGradeLevels">
                    <label class="form-check-label" for="cloneGradeLevels">{{ __('Grade levels') }}</label>
                </div>
                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="cloneHouses" wire:model="cloneHouses">
                    <label class="form-check-label" for="cloneHouses">{{ __('Houses') }}</label>
                </div>

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Copy now') }}</button>
            </form>
        </div>
    </div>
</div>
