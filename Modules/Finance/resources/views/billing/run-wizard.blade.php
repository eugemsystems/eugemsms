<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.billing.history', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('New billing run') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Narrow the scope below, or leave everything blank to bill every active learner.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="compute">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="sectionId" wire:model="sectionId">
                                <option value="">{{ __('Any section') }}</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                            <label for="sectionId">{{ __('Section') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="gradeLevelId" wire:model="gradeLevelId">
                                <option value="">{{ __('Any grade level') }}</option>
                                @foreach ($gradeLevels as $gradeLevel)
                                    <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                @endforeach
                            </select>
                            <label for="gradeLevelId">{{ __('Grade level') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="classId" wire:model="classId">
                                <option value="">{{ __('Any class') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                            <label for="classId">{{ __('Class') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="enrolmentType" wire:model="enrolmentType">
                                <option value="">{{ __('Any enrolment type') }}</option>
                                <option value="FULL_TIME">{{ __('Full-time') }}</option>
                                <option value="PART_TIME">{{ __('Part-time') }}</option>
                            </select>
                            <label for="enrolmentType">{{ __('Enrolment type') }}</label>
                        </div>
                    </div>
                </div>

                @error('sectionId') <div class="alert alert-danger">{{ $message }}</div> @enderror

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:confirm="{{ __('Compute this billing run now? Nothing is invoiced yet — you\'ll review a preview first.') }}">
                    {{ __('Compute') }}
                </button>
            </form>
        </div>
    </div>
</div>
