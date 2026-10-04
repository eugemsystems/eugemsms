<div>
    <h4 class="mb-4">{{ __('New application') }}</h4>

    <form wire:submit="save">
        <div class="card mb-4">
            <div class="card-header">{{ __('Applicant') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('intakeId') is-invalid @enderror" wire:model="intakeId">
                                <option value="">{{ __('Select an intake') }}</option>
                                @foreach ($intakes as $intake)
                                    <option value="{{ $intake->id }}">{{ $intake->name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('Intake') }}</label>
                            @error('intakeId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('firstName') is-invalid @enderror" wire:model="firstName" placeholder=" ">
                            <label>{{ __('First name') }}</label>
                            @error('firstName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('lastName') is-invalid @enderror" wire:model="lastName" placeholder=" ">
                            <label>{{ __('Last name') }}</label>
                            @error('lastName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('dateOfBirth') is-invalid @enderror" wire:model="dateOfBirth">
                            <label>{{ __('Date of birth') }}</label>
                            @error('dateOfBirth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model="gender">
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                            <label>{{ __('Gender') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="nationality" placeholder=" ">
                            <label>{{ __('Nationality (ISO2)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('requestedGradeLevelId') is-invalid @enderror" wire:model="requestedGradeLevelId">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($gradeLevels as $gradeLevel)
                                    <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('Requested grade level') }}</label>
                            @error('requestedGradeLevelId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model.live="requestedEnrolmentType">
                                <option value="FULL_TIME">{{ __('Full time') }}</option>
                                <option value="PART_TIME">{{ __('Part time') }}</option>
                            </select>
                            <label>{{ __('Enrolment type') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model="requestedResidency">
                                <option value="DAY">{{ __('Day') }}</option>
                                <option value="BOARDER">{{ __('Boarder') }}</option>
                                <option value="WEEKLY_BOARDER">{{ __('Weekly boarder') }}</option>
                            </select>
                            <label>{{ __('Residency') }}</label>
                        </div>
                    </div>

                    @if ($requestedEnrolmentType === 'PART_TIME')
                        <div class="col-12">
                            <label class="form-label">{{ __('Requested subjects') }}</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($subjects as $subject)
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" value="{{ $subject->id }}" wire:model="requestedSubjectIds" id="subject-{{ $subject->id }}">
                                        <label class="form-check-label" for="subject-{{ $subject->id }}">{{ $subject->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="hasSiblingAtSchool" wire:model="hasSiblingAtSchool">
                            <label class="form-check-label" for="hasSiblingAtSchool">{{ __('Has a sibling at this school') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                {{ __('Guardians') }}
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addGuardian">{{ __('Add guardian') }}</button>
            </div>
            <div class="card-body">
                @foreach ($guardians as $index => $guardian)
                    <div class="row g-2 align-items-end mb-2" wire:key="guardian-row-{{ $index }}">
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" wire:model="guardians.{{ $index }}.relationship">
                                <option value="mother">{{ __('Mother') }}</option>
                                <option value="father">{{ __('Father') }}</option>
                                <option value="guardian">{{ __('Guardian') }}</option>
                                <option value="sponsor">{{ __('Sponsor') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control form-control-sm" wire:model="guardians.{{ $index }}.first_name" placeholder="{{ __('First name') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control form-control-sm" wire:model="guardians.{{ $index }}.last_name" placeholder="{{ __('Last name') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control form-control-sm" wire:model="guardians.{{ $index }}.primary_phone" placeholder="{{ __('Phone') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="email" class="form-control form-control-sm" wire:model="guardians.{{ $index }}.email" placeholder="{{ __('Email') }}">
                        </div>
                        <div class="col-md-1 form-check">
                            <input type="checkbox" class="form-check-input" wire:model="guardians.{{ $index }}.is_fee_responsible" id="fee-{{ $index }}">
                            <label class="form-check-label small" for="fee-{{ $index }}">{{ __('Fee') }}</label>
                        </div>
                        <div class="col-md-1">
                            @if (count($guardians) > 1)
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeGuardian({{ $index }})">{{ __('Remove') }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Submit application') }}</button>
    </form>
</div>
